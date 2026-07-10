/* Surfaces (RFC prototype) — runtime access predicate and link-time
 * validation. See zend_surfaces.h for the metadata model. */

#include "zend.h"
#include "zend_API.h"
#include "zend_compile.h"
#include "zend_execute.h"
#include "zend_constants.h"
#include "zend_globals.h"
#include "zend_interfaces.h"
#include "zend_operators.h"
#include "zend_surfaces.h"

ZEND_API zend_class_entry *zend_surfaces_find_owner(
	const zend_class_entry *ce, const zend_string *surface_name)
{
	while (ce) {
		if (ce->surface_decls
		 && zend_hash_exists(ce->surface_decls, (zend_string *) surface_name)) {
			return (zend_class_entry *) ce;
		}
		ce = ce->parent;
	}
	return NULL;
}

static zend_class_entry *surfaces_find_owner_str(
	const zend_class_entry *ce, const char *name, size_t len)
{
	while (ce) {
		if (ce->surface_decls
		 && zend_hash_str_exists(ce->surface_decls, name, len)) {
			return (zend_class_entry *) ce;
		}
		ce = ce->parent;
	}
	return NULL;
}

ZEND_API zval *zend_surfaces_member_set_str(
	const zend_class_entry *owner, char kind, const char *member_name, size_t len)
{
	if (!owner->surface_members) {
		return NULL;
	}

	ALLOCA_FLAG(use_heap)
	zend_string *key;
	ZSTR_ALLOCA_ALLOC(key, len + 2, use_heap);
	ZSTR_VAL(key)[0] = kind;
	ZSTR_VAL(key)[1] = ':';
	memcpy(ZSTR_VAL(key) + 2, member_name, len);
	ZSTR_VAL(key)[len + 2] = '\0';

	zval *set = zend_hash_find(owner->surface_members, key);
	ZSTR_ALLOCA_FREE(key, use_heap);
	return set;
}

ZEND_API zval *zend_surfaces_member_set(
	const zend_class_entry *owner, char kind, const zend_string *member_name)
{
	return zend_surfaces_member_set_str(
		owner, kind, ZSTR_VAL(member_name), ZSTR_LEN(member_name));
}

static bool surfaces_set_contains(const zval *surface_set, const char *name, size_t len)
{
	zval *entry;
	ZEND_HASH_PACKED_FOREACH_VAL(Z_ARR_P(surface_set), entry) {
		if (zend_string_equals_cstr(Z_STR_P(entry), name, len)) {
			return true;
		}
	} ZEND_HASH_FOREACH_END();
	return false;
}

/* The op_array whose code performed the access: nearest user frame. */
static const zend_op_array *surfaces_calling_op_array(void)
{
	const zend_execute_data *ex = EG(current_execute_data);
	while (ex) {
		if (ex->func && ZEND_USER_CODE(ex->func->common.type)) {
			return &ex->func->op_array;
		}
		ex = ex->prev_execute_data;
	}
	return NULL;
}

ZEND_API bool zend_surfaces_access_allowed(
	const zend_class_entry *owner, const zend_class_entry *receiver_ce,
	const zval *surface_set)
{
	/* Hierarchy auto-hold: the declaring class and its subclasses reach
	 * their own surface members without a grant, exactly like protected. */
	const zend_class_entry *scope =
		EG(fake_scope) ? EG(fake_scope) : zend_get_executed_scope();
	if (scope && instanceof_function(scope, owner)) {
		return true;
	}

	const zend_op_array *op_array = surfaces_calling_op_array();
	if (!op_array || !op_array->surface_grants) {
		return false;
	}

	zval *entry;
	ZEND_HASH_PACKED_FOREACH_VAL(op_array->surface_grants, entry) {
		const char *grant = Z_STRVAL_P(entry);
		const char *sep = strchr(grant, ':');
		if (!sep) {
			continue;
		}
		size_t class_len = (size_t) (sep - grant);
		const char *surface = sep + 1;
		size_t surface_len = Z_STRLEN_P(entry) - class_len - 1;

		/* The grant covers receivers of the named class or a subtype. No
		 * autoload: if the granted class is not loaded, it cannot be an
		 * ancestor of the (loaded) receiver. */
		zend_class_entry *granted =
			zend_hash_str_find_ptr(EG(class_table), grant, class_len);
		if (!granted || !instanceof_function(receiver_ce, granted)) {
			continue;
		}
		/* The named surface must exist on the granted class (declared or
		 * inherited), and the member must be on it. */
		if (!surfaces_find_owner_str(granted, surface, surface_len)) {
			continue;
		}
		if (surfaces_set_contains(surface_set, surface, surface_len)) {
			return true;
		}
	} ZEND_HASH_FOREACH_END();

	return false;
}

/* A surface method that implements a method of a surface-bound interface
 * has a public face: any holder of the interface type may call it freely
 * (the RFC gates the *conversion* to the interface, not downstream use).
 * A runtime check cannot see whether the call site held the interface or
 * the concrete class, so such members are exempt from the member gate; the
 * conversion gate is deferred (see RFC notes). */
ZEND_API bool zend_surfaces_method_has_interface_face(const zend_function *fbc)
{
	const zend_function *proto = fbc->common.prototype;
	if (!proto || !proto->common.scope
	 || !(proto->common.scope->ce_flags & ZEND_ACC_INTERFACE)) {
		return false;
	}
	const zend_class_entry *iface = proto->common.scope;
	for (const zend_class_entry *ce = fbc->common.scope; ce; ce = ce->parent) {
		if (!ce->surface_decls) {
			continue;
		}
		zval *iface_zv;
		ZEND_HASH_FOREACH_VAL(ce->surface_decls, iface_zv) {
			if (Z_TYPE_P(iface_zv) == IS_STRING
			 && zend_string_equals_ci(Z_STR_P(iface_zv), iface->name)) {
				return true;
			}
		} ZEND_HASH_FOREACH_END();
	}
	return false;
}

/* ---------------------------------------------------------------------- */
/* Link-time validation                                                    */
/* ---------------------------------------------------------------------- */

static void surfaces_check_member_names(zend_class_entry *ce)
{
	zend_string *key;
	zval *set;

	ZEND_HASH_FOREACH_STR_KEY_VAL(ce->surface_members, key, set) {
		char kind = ZSTR_VAL(key)[0];
		const char *mname = ZSTR_VAL(key) + 2;
		size_t mlen = ZSTR_LEN(key) - 2;

		/* Is this member an override of an inherited member? If so, fetch
		 * the parent member's surface set from its declaring class. */
		bool is_override = false;
		const zval *parent_set = NULL;
		if (ce->parent) {
			const zend_class_entry *decl_ce = NULL;
			switch (kind) {
				case 'm': {
					zend_function *pf = zend_hash_str_find_ptr(
						&ce->parent->function_table, mname, mlen);
					if (pf) {
						is_override = true;
						decl_ce = pf->common.scope;
					}
					break;
				}
				case 'p': {
					zend_property_info *pp = zend_hash_str_find_ptr(
						&ce->parent->properties_info, mname, mlen);
					if (pp && !(pp->flags & ZEND_ACC_PRIVATE)) {
						is_override = true;
						decl_ce = pp->ce;
					}
					break;
				}
				case 'c': {
					zend_class_constant *pc = zend_hash_str_find_ptr(
						&ce->parent->constants_table, mname, mlen);
					if (pc && !(ZEND_CLASS_CONST_FLAGS(pc) & ZEND_ACC_PRIVATE)) {
						is_override = true;
						decl_ce = pc->ce;
					}
					break;
				}
				default:
					ZEND_UNREACHABLE();
			}
			if (decl_ce) {
				zend_string *plain = zend_string_init(mname, mlen, 0);
				parent_set = zend_surfaces_member_set(decl_ce, kind, plain);
				zend_string_release(plain);
			}
		}

		/* Each named surface must exist in the hierarchy; a non-override may
		 * only use surfaces this class itself declares; an override may keep
		 * the parent's surfaces and add only its own. */
		zval *name_zv;
		ZEND_HASH_PACKED_FOREACH_VAL(Z_ARR_P(set), name_zv) {
			zend_string *sname = Z_STR_P(name_zv);
			const zend_class_entry *sowner = zend_surfaces_find_owner(ce, sname);
			if (!sowner) {
				zend_error_noreturn(E_COMPILE_ERROR,
					"Undeclared surface %s on member %.*s of class %s",
					ZSTR_VAL(sname), (int) mlen, mname, ZSTR_VAL(ce->name));
			}
			if (sowner != ce
			 && !(is_override && parent_set
					&& surfaces_set_contains(parent_set, ZSTR_VAL(sname), ZSTR_LEN(sname)))) {
				zend_error_noreturn(E_COMPILE_ERROR,
					"Cannot add member %.*s to surface %s inherited from %s "
					"(a subclass may not extend a surface it does not own)",
					(int) mlen, mname, ZSTR_VAL(sname), ZSTR_VAL(sowner->name));
			}
		} ZEND_HASH_FOREACH_END();

		/* Override superset rule: the override's accessibility set must
		 * include every surface of the parent member. */
		if (is_override && parent_set) {
			ZEND_HASH_PACKED_FOREACH_VAL(Z_ARR_P((zval *) parent_set), name_zv) {
				zend_string *sname = Z_STR_P(name_zv);
				if (!surfaces_set_contains(set, ZSTR_VAL(sname), ZSTR_LEN(sname))) {
					zend_error_noreturn(E_COMPILE_ERROR,
						"Override of %.*s in %s must keep surface %s "
						"(an override may widen accessibility but not drop a surface)",
						(int) mlen, mname, ZSTR_VAL(ce->name), ZSTR_VAL(sname));
				}
			} ZEND_HASH_FOREACH_END();
		}
	} ZEND_HASH_FOREACH_END();
}

/* Conformance of a surface-bound interface, scoped to (that surface's
 * members ∪ the class's public members): a member on a different surface
 * does not satisfy the interface. Ordinary signature/existence conformance
 * was already checked by the normal implements machinery. */
static void surfaces_check_interface_conformance(zend_class_entry *ce)
{
	zend_string *surface_name;
	zval *iface_zv;

	ZEND_HASH_FOREACH_STR_KEY_VAL(ce->surface_decls, surface_name, iface_zv) {
		if (Z_TYPE_P(iface_zv) != IS_STRING) {
			continue;
		}
		zend_class_entry *iface = NULL;
		for (uint32_t i = 0; i < ce->num_interfaces; i++) {
			if (zend_string_equals_ci(ce->interfaces[i]->name, Z_STR_P(iface_zv))) {
				iface = ce->interfaces[i];
				break;
			}
		}
		if (!iface) {
			continue;
		}

		zend_string *mname;
		ZEND_HASH_MAP_FOREACH_STR_KEY(&iface->function_table, mname) {
			const zend_function *impl = zend_hash_find_ptr(&ce->function_table, mname);
			if (!impl || !impl->common.scope) {
				continue;
			}
			const zval *mset = zend_surfaces_member_set(impl->common.scope, 'm', mname);
			if (mset && !surfaces_set_contains(mset,
					ZSTR_VAL(surface_name), ZSTR_LEN(surface_name))) {
				zend_error_noreturn(E_COMPILE_ERROR,
					"Method %s::%s() satisfies interface %s bound to surface %s "
					"but is on a different surface (an interface cannot be "
					"spanned across surfaces)",
					ZSTR_VAL(ce->name), ZSTR_VAL(mname),
					ZSTR_VAL(iface->name), ZSTR_VAL(surface_name));
			}
		} ZEND_HASH_FOREACH_END();
	} ZEND_HASH_FOREACH_END();
}

void zend_surfaces_link_class(zend_class_entry *ce)
{
	if (ce->ce_flags & (ZEND_ACC_INTERFACE|ZEND_ACC_TRAIT)) {
		return;
	}

	/* Surface ownership is fixed hierarchy-wide: no redeclaration. */
	if (ce->surface_decls && ce->parent) {
		zend_string *name;
		ZEND_HASH_FOREACH_STR_KEY(ce->surface_decls, name) {
			const zend_class_entry *prior = zend_surfaces_find_owner(ce->parent, name);
			if (prior) {
				zend_error_noreturn(E_COMPILE_ERROR,
					"Cannot redeclare surface %s on %s (owned by %s)",
					ZSTR_VAL(name), ZSTR_VAL(ce->name), ZSTR_VAL(prior->name));
			}
		} ZEND_HASH_FOREACH_END();
	}

	if (ce->surface_members) {
		surfaces_check_member_names(ce);
	}

	if (ce->surface_decls && (ce->ce_flags & ZEND_ACC_RESOLVED_INTERFACES)) {
		surfaces_check_interface_conformance(ce);
	}
}
