/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Monomorphized class generics: runtime stamping of instantiations     |
   | from generic templates (prototype).                                  |
   |                                                                      |
   | An instantiation "App\Vec<Foo,int>" is a fresh zend_class_entry that |
   | shares the template's op_array bodies (opcodes, literals) and clones |
   | the headers with rebound scope, plus type-substituted arg_info,      |
   | property and constant types -- so ordinary typed-property/parameter  |
   | enforcement provides runtime type safety at zero hot-path cost.      |
   +----------------------------------------------------------------------+
*/

#include "zend.h"
#include "zend_API.h"
#include "zend_compile.h"
#include "zend_exceptions.h"
#include "zend_execute.h"
#include "zend_generics.h"
#include "zend_inheritance.h"
#include "zend_interfaces.h"
#include "zend_operators.h"

#define ZEND_GENERICS_MAX_ARGS 64

typedef struct {
	const char *start;
	size_t len;
} zend_generic_name_slice;

/* Strict structural parse of a mangled name: BASE '<' ARG (',' ARG)* '>'
 * with no whitespace; args may nest. Returns false on any malformation. */
static bool zend_generics_parse_name(
		const zend_string *name, zend_generic_name_slice *base,
		zend_generic_name_slice *args, uint32_t *num_args)
{
	const char *s = ZSTR_VAL(name);
	size_t len = ZSTR_LEN(name);
	const char *lt = memchr(s, '<', len);

	if (!lt || lt == s || s[len - 1] != '>') {
		return false;
	}
	base->start = s;
	base->len = lt - s;
	if (memchr(base->start, '>', base->len)) {
		return false;
	}

	uint32_t depth = 0;
	uint32_t count = 0;
	const char *arg_start = lt + 1;
	for (const char *p = lt + 1; p < s + len; p++) {
		char c = *p;
		if (c == '<') {
			depth++;
		} else if (c == '>') {
			if (depth == 0) {
				if (p != s + len - 1) {
					return false; /* content after the closing '>' */
				}
				if (p == arg_start || count >= ZEND_GENERICS_MAX_ARGS) {
					return false;
				}
				args[count].start = arg_start;
				args[count].len = p - arg_start;
				count++;
				break;
			}
			depth--;
		} else if (c == ',' && depth == 0) {
			if (p == arg_start || count >= ZEND_GENERICS_MAX_ARGS) {
				return false;
			}
			args[count].start = arg_start;
			args[count].len = p - arg_start;
			count++;
			arg_start = p + 1;
		} else if (!((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z')
				|| (c >= '0' && c <= '9') || c == '_' || c == '\\'
				|| (unsigned char) c >= 0x80 || c == ',')) {
			return false;
		}
	}
	if (depth != 0 || count == 0) {
		return false;
	}
	*num_args = count;
	return true;
}

static uint32_t zend_generics_scalar_mask(const zend_generic_name_slice *slice)
{
	switch (slice->len) {
		case 3:
			if (zend_binary_strcasecmp(slice->start, 3, "int", 3) == 0) return MAY_BE_LONG;
			break;
		case 4:
			if (zend_binary_strcasecmp(slice->start, 4, "bool", 4) == 0) return MAY_BE_BOOL;
			break;
		case 5:
			if (zend_binary_strcasecmp(slice->start, 5, "float", 5) == 0) return MAY_BE_DOUBLE;
			break;
		case 6:
			if (zend_binary_strcasecmp(slice->start, 6, "string", 6) == 0) return MAY_BE_STRING;
			break;
	}
	return 0;
}

/* Returns the index of the template type parameter a single-label class-name
 * string refers to, or (uint32_t)-1. */
static uint32_t zend_generics_param_index(
		const zend_class_entry *template_ce, const zend_string *type_name)
{
	const zend_generic_params *gp = template_ce->generic_params;
	if (memchr(ZSTR_VAL(type_name), '\\', ZSTR_LEN(type_name))) {
		return (uint32_t) -1;
	}
	for (uint32_t i = 0; i < gp->num_params; i++) {
		if (zend_string_equals_ci(gp->params[i].name, type_name)) {
			return i;
		}
	}
	return (uint32_t) -1;
}

/* Local equivalent of zend_inheritance.c's zend_type_copy_ctor (which is
 * static there): arena-duplicate lists; addref name strings only when the
 * copy will be released again (property/constant types are, substituted
 * arg_info entries never are). */
static void zend_generics_type_copy_ctor(zend_type *type, bool take_refs)
{
	if (ZEND_TYPE_HAS_LIST(*type)) {
		const zend_type_list *old_list = ZEND_TYPE_LIST(*type);
		size_t size = ZEND_TYPE_LIST_SIZE(old_list->num_types);
		zend_type_list *new_list = zend_arena_alloc(&CG(arena), size);

		memcpy(new_list, old_list, size);
		ZEND_TYPE_SET_LIST(*type, new_list);
		ZEND_TYPE_FULL_MASK(*type) |= _ZEND_TYPE_ARENA_BIT;

		zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH_MUTABLE(new_list, list_type) {
			if (ZEND_TYPE_HAS_LIST(*list_type)) {
				zend_generics_type_copy_ctor(list_type, take_refs);
			} else if (take_refs && ZEND_TYPE_HAS_NAME(*list_type)) {
				zend_string_addref(ZEND_TYPE_NAME(*list_type));
			}
		} ZEND_TYPE_LIST_FOREACH_END();
	} else if (take_refs && ZEND_TYPE_HAS_NAME(*type)) {
		zend_string_addref(ZEND_TYPE_NAME(*type));
	}
}

/* Substitute template type parameters in a single-name type in place.
 * Returns true if a substitution happened. The result owns its strings. */
static bool zend_generics_substitute_single(
		zend_type *type, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, bool take_refs)
{
	if (!ZEND_TYPE_HAS_NAME(*type)) {
		return false;
	}
	uint32_t idx = zend_generics_param_index(template_ce, ZEND_TYPE_NAME(*type));
	if (idx == (uint32_t) -1) {
		return false;
	}

	const zend_type arg = binding->args[idx];
	/* Preserve surrounding MAY_BE_* bits (e.g. nullability of "?T"). */
	uint32_t extra_mask = ZEND_TYPE_FULL_MASK(*type) & _ZEND_TYPE_MAY_BE_MASK;

	if (ZEND_TYPE_HAS_NAME(arg)) {
		type->ptr = take_refs
			? zend_string_copy(ZEND_TYPE_NAME(arg)) : ZEND_TYPE_NAME(arg);
		type->type_mask = _ZEND_TYPE_NAME_BIT | extra_mask;
	} else {
		type->ptr = NULL;
		type->type_mask = ZEND_TYPE_PURE_MASK(arg) | extra_mask;
	}
	return true;
}

/* Does `type` reference any template parameter (at any list depth)? */
static bool zend_generics_type_uses_params(
		zend_type type, const zend_class_entry *template_ce)
{
	if (ZEND_TYPE_HAS_LIST(type)) {
		const zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(type), list_type) {
			if (zend_generics_type_uses_params(*list_type, template_ce)) {
				return true;
			}
		} ZEND_TYPE_LIST_FOREACH_END();
		return false;
	}
	return ZEND_TYPE_HAS_NAME(type)
		&& zend_generics_param_index(template_ce, ZEND_TYPE_NAME(type)) != (uint32_t) -1;
}

/* Copy `type` with substitution applied, owning all strings. Throws (and
 * returns false) if a scalar argument would land inside a composite type. */
static bool zend_generics_substitute_type(
		zend_type *type, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, const zend_string *display_name,
		bool take_refs)
{
	if (ZEND_TYPE_HAS_LIST(*type)) {
		zend_generics_type_copy_ctor(type, take_refs);
		zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH_MUTABLE(ZEND_TYPE_LIST(*type), list_type) {
			if (ZEND_TYPE_HAS_NAME(*list_type)) {
				uint32_t idx = zend_generics_param_index(template_ce, ZEND_TYPE_NAME(*list_type));
				if (idx != (uint32_t) -1) {
					if (!ZEND_TYPE_HAS_NAME(binding->args[idx])) {
						zend_throw_error(NULL,
							"Cannot stamp %s: scalar type argument for parameter %s "
							"is used inside a composite type",
							ZSTR_VAL(display_name),
							ZSTR_VAL(template_ce->generic_params->params[idx].name));
						return false;
					}
					if (take_refs) {
						zend_string_release(ZEND_TYPE_NAME(*list_type));
						list_type->ptr = zend_string_copy(ZEND_TYPE_NAME(binding->args[idx]));
					} else {
						list_type->ptr = ZEND_TYPE_NAME(binding->args[idx]);
					}
				}
			}
		} ZEND_TYPE_LIST_FOREACH_END();
		return true;
	}
	if (!zend_generics_substitute_single(type, template_ce, binding, take_refs)) {
		zend_generics_type_copy_ctor(type, take_refs);
	}
	return true;
}

static zend_op_array *zend_generics_clone_method(
		zend_op_array *tpl_fn, zend_class_entry *ce,
		const zend_class_entry *template_ce, const zend_generic_binding *binding,
		const zend_string *display_name)
{
	ZEND_ASSERT(tpl_fn->type == ZEND_USER_FUNCTION);
	zend_op_array *new_fn = zend_arena_alloc(&CG(arena), sizeof(zend_op_array));
	memcpy(new_fn, tpl_fn, sizeof(zend_op_array));
	if (new_fn->refcount) {
		(*new_fn->refcount)++;
	}
	if (new_fn->function_name) {
		zend_string_addref(new_fn->function_name);
	}
	new_fn->scope = ce;
	new_fn->fn_flags &= ~ZEND_ACC_IMMUTABLE;
	ZEND_MAP_PTR_INIT(new_fn->run_time_cache, NULL);
	ZEND_MAP_PTR_INIT(new_fn->static_variables_ptr, NULL);

	/* Substitute type parameters in the signature, if any occur. */
	if (tpl_fn->arg_info) {
		uint32_t total = tpl_fn->num_args;
		uint32_t has_ret = (tpl_fn->fn_flags & ZEND_ACC_HAS_RETURN_TYPE) ? 1 : 0;
		zend_arg_info *tpl_base = tpl_fn->arg_info - has_ret;
		total += has_ret;
		if (tpl_fn->fn_flags & ZEND_ACC_VARIADIC) {
			total++;
		}

		bool uses_params = false;
		for (uint32_t i = 0; i < total; i++) {
			if (zend_generics_type_uses_params(tpl_base[i].type, template_ce)) {
				uses_params = true;
				break;
			}
		}

		if (uses_params) {
			char *block = zend_arena_alloc(&CG(arena),
				sizeof(zend_arg_info *) + total * sizeof(zend_arg_info));
			/* Stash the shared original so destroy_op_array can restore it
			 * before the final free (see ZEND_ACC2_GENERIC_SUBST_ARG_INFO). */
			*(zend_arg_info **) block = tpl_fn->arg_info;
			zend_arg_info *entries = (zend_arg_info *) (block + sizeof(zend_arg_info *));
			memcpy(entries, tpl_base, total * sizeof(zend_arg_info));
			for (uint32_t i = 0; i < total; i++) {
				/* These entries are never destroyed (the template's original
				 * arg_info is restored before the final free), so they must
				 * not take string references. */
				if (!zend_generics_substitute_type(
						&entries[i].type, template_ce, binding, display_name,
						/* take_refs */ false)) {
					return NULL;
				}
			}
			new_fn->arg_info = entries + has_ret;
			new_fn->fn_flags2 |= ZEND_ACC2_GENERIC_SUBST_ARG_INFO;
		}
	}
	return new_fn;
}

#define zend_generics_update_inherited_handler(handler) do { \
		if (ce->handler == (zend_function *) tpl_fn) { \
			ce->handler = (zend_function *) new_fn; \
		} \
	} while (0)

static zend_class_entry *zend_generics_stamp_ce(
		zend_class_entry *template_ce, zend_string *display_name,
		zend_string *lc_key, zend_generic_binding *binding)
{
	zend_class_entry *ce = zend_arena_alloc(&CG(arena), sizeof(zend_class_entry));

	memcpy(ce, template_ce, sizeof(zend_class_entry));
	ce->name = zend_string_copy(display_name);
	ce->refcount = 1;
	ce->inheritance_cache = NULL;
	/* The instance owns its tables and must take the full destroy path even
	 * when the template came from SHM or the file cache. */
	ce->ce_flags &= ~(ZEND_ACC_IMMUTABLE | ZEND_ACC_FILE_CACHED);
	ce->ce_flags2 = (ce->ce_flags2 & ~ZEND_ACC2_GENERIC_TEMPLATE) | ZEND_ACC2_GENERIC_INSTANCE;
	ce->generic_params = NULL;
	ce->generic_binding = binding;
	ZEND_MAP_PTR_INIT(ce->mutable_data, NULL);
	ZEND_MAP_PTR_INIT(ce->static_members_table, NULL);

	/* Per-instance ownership of everything destroy_zend_class releases. */
	zend_string_addref(ce->info.user.filename);
	if (ce->doc_comment) {
		zend_string_addref(ce->doc_comment);
	}
	if (ce->attributes && !(GC_FLAGS(ce->attributes) & IS_ARRAY_IMMUTABLE)) {
		/* Immutable (SHM) tables are shared without refcounting; guarded
		 * so no writes land in shared memory. zend_hash_release skips them. */
		GC_ADDREF(ce->attributes);
	}
	/* Trait metadata stays owned by the template (methods were merged at
	 * link time; the clones below pick them up from the function table). */
	ce->num_traits = 0;
	ce->trait_names = NULL;
	ce->trait_aliases = NULL;
	ce->trait_precedences = NULL;

	if (ce->num_interfaces > 0) {
		ZEND_ASSERT(ce->ce_flags & ZEND_ACC_RESOLVED_INTERFACES);
		zend_class_entry **interfaces = emalloc(sizeof(zend_class_entry *) * ce->num_interfaces);
		memcpy(interfaces, template_ce->interfaces, sizeof(zend_class_entry *) * ce->num_interfaces);
		ce->interfaces = interfaces;
	}

	/* default property values */
	if (ce->default_properties_table) {
		zval *dst = emalloc(sizeof(zval) * ce->default_properties_count);
		zval *src = ce->default_properties_table;
		zval *end = src + ce->default_properties_count;

		ce->default_properties_table = dst;
		for (; src != end; src++, dst++) {
			ZVAL_COPY_PROP(dst, src);
		}
	}

	/* methods */
	ce->function_table.pDestructor = ZEND_FUNCTION_DTOR;
	if (!(HT_FLAGS(&ce->function_table) & HASH_FLAG_UNINITIALIZED)) {
		Bucket *p = emalloc(HT_SIZE(&ce->function_table));
		memcpy(p, HT_GET_DATA_ADDR(&ce->function_table), HT_USED_SIZE(&ce->function_table));
		HT_SET_DATA_ADDR(&ce->function_table, p);
		p = ce->function_table.arData;
		const Bucket *end = p + ce->function_table.nNumUsed;
		for (; p != end; p++) {
			zend_string_addref(p->key);
			zend_op_array *tpl_fn = Z_PTR(p->val);
			zend_op_array *new_fn = zend_generics_clone_method(
				tpl_fn, ce, template_ce, binding, display_name);
			if (!new_fn) {
				return NULL;
			}
			Z_PTR(p->val) = new_fn;

			zend_generics_update_inherited_handler(constructor);
			zend_generics_update_inherited_handler(destructor);
			zend_generics_update_inherited_handler(clone);
			zend_generics_update_inherited_handler(__get);
			zend_generics_update_inherited_handler(__set);
			zend_generics_update_inherited_handler(__call);
			zend_generics_update_inherited_handler(__isset);
			zend_generics_update_inherited_handler(__unset);
			zend_generics_update_inherited_handler(__tostring);
			zend_generics_update_inherited_handler(__callstatic);
			zend_generics_update_inherited_handler(__debugInfo);
			zend_generics_update_inherited_handler(__serialize);
			zend_generics_update_inherited_handler(__unserialize);
		}
	}

	/* static members */
	if (ce->default_static_members_table) {
		zval *dst = emalloc(sizeof(zval) * ce->default_static_members_count);
		zval *src = ce->default_static_members_table;
		zval *end = src + ce->default_static_members_count;

		ce->default_static_members_table = dst;
		for (; src != end; src++, dst++) {
			ZVAL_COPY(dst, src);
		}
	}

	/* properties_info: rebound + type-substituted */
	if (!(HT_FLAGS(&ce->properties_info) & HASH_FLAG_UNINITIALIZED)) {
		Bucket *p = emalloc(HT_SIZE(&ce->properties_info));
		memcpy(p, HT_GET_DATA_ADDR(&ce->properties_info), HT_USED_SIZE(&ce->properties_info));
		HT_SET_DATA_ADDR(&ce->properties_info, p);
		p = ce->properties_info.arData;
		const Bucket *end = p + ce->properties_info.nNumUsed;
		for (; p != end; p++) {
			zend_string_addref(p->key);
			const zend_property_info *prop_info = Z_PTR(p->val);
			zend_property_info *new_prop_info =
				zend_arena_alloc(&CG(arena), sizeof(zend_property_info));
			Z_PTR(p->val) = new_prop_info;
			memcpy(new_prop_info, prop_info, sizeof(zend_property_info));
			new_prop_info->ce = ce;
			if (prop_info->prototype == prop_info) {
				new_prop_info->prototype = new_prop_info;
			}
			zend_string_addref(new_prop_info->name);
			if (new_prop_info->doc_comment) {
				zend_string_addref(new_prop_info->doc_comment);
			}
			if (new_prop_info->attributes
					&& !(GC_FLAGS(new_prop_info->attributes) & IS_ARRAY_IMMUTABLE)) {
				GC_ADDREF(new_prop_info->attributes);
			}
			if (!zend_generics_substitute_type(
					&new_prop_info->type, template_ce, binding, display_name,
					/* take_refs */ true)) {
				return NULL;
			}
			if (new_prop_info->hooks) {
				new_prop_info->hooks = zend_arena_alloc(&CG(arena), ZEND_PROPERTY_HOOK_STRUCT_SIZE);
				memcpy(new_prop_info->hooks, prop_info->hooks, ZEND_PROPERTY_HOOK_STRUCT_SIZE);
				for (uint32_t i = 0; i < ZEND_PROPERTY_HOOK_COUNT; i++) {
					if (new_prop_info->hooks[i]) {
						zend_op_array *hook = zend_generics_clone_method(
							(zend_op_array *) new_prop_info->hooks[i], ce,
							template_ce, binding, display_name);
						if (!hook) {
							return NULL;
						}
						hook->prop_info = new_prop_info;
						new_prop_info->hooks[i] = (zend_function *) hook;
					}
				}
			}
		}
	}
	ce->properties_info_table = NULL;
	if (ce->default_properties_count) {
		zend_build_properties_info_table(ce);
	}

	/* constants */
	if (!(HT_FLAGS(&ce->constants_table) & HASH_FLAG_UNINITIALIZED)) {
		Bucket *p = emalloc(HT_SIZE(&ce->constants_table));
		memcpy(p, HT_GET_DATA_ADDR(&ce->constants_table), HT_USED_SIZE(&ce->constants_table));
		HT_SET_DATA_ADDR(&ce->constants_table, p);
		p = ce->constants_table.arData;
		const Bucket *end = p + ce->constants_table.nNumUsed;
		for (; p != end; p++) {
			zend_string_addref(p->key);
			const zend_class_constant *c = Z_PTR(p->val);
			zend_class_constant *new_c =
				zend_arena_alloc(&CG(arena), sizeof(zend_class_constant));
			Z_PTR(p->val) = new_c;
			memcpy(new_c, c, sizeof(zend_class_constant));
			new_c->ce = ce;
			Z_TRY_ADDREF(new_c->value);
			if (new_c->doc_comment) {
				zend_string_addref(new_c->doc_comment);
			}
			if (new_c->attributes
					&& !(GC_FLAGS(new_c->attributes) & IS_ARRAY_IMMUTABLE)) {
				GC_ADDREF(new_c->attributes);
			}
			if (!zend_generics_substitute_type(
					&new_c->type, template_ce, binding, display_name,
					/* take_refs */ true)) {
				return NULL;
			}
		}
	}

	return ce;
}

static bool zend_generics_check_bounds(
		const zend_class_entry *template_ce, const zend_generic_binding *binding,
		const zend_string *display_name, bool use_autoload)
{
	const zend_generic_params *gp = template_ce->generic_params;
	uint32_t lookup_flags = use_autoload ? 0 : ZEND_FETCH_CLASS_NO_AUTOLOAD;

	for (uint32_t i = 0; i < gp->num_params; i++) {
		const zend_generic_param *param = &gp->params[i];
		if (!param->bound_name) {
			continue;
		}
		const zend_type arg = binding->args[i];
		if (!ZEND_TYPE_HAS_NAME(arg)) {
			zend_throw_error(NULL,
				"Cannot stamp %s: scalar type argument does not satisfy the bound %s "
				"of type parameter %s", ZSTR_VAL(display_name),
				ZSTR_VAL(param->bound_name), ZSTR_VAL(param->name));
			return false;
		}

		zend_class_entry *arg_ce = zend_lookup_class_ex(ZEND_TYPE_NAME(arg), NULL, lookup_flags);
		if (!arg_ce) {
			if (!EG(exception)) {
				zend_throw_error(NULL,
					"Cannot stamp %s: class %s for type parameter %s was not found",
					ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(arg)),
					ZSTR_VAL(param->name));
			}
			return false;
		}
		zend_class_entry *bound_ce = zend_lookup_class_ex(param->bound_name, NULL, lookup_flags);
		if (!bound_ce) {
			if (!EG(exception)) {
				zend_throw_error(NULL,
					"Cannot stamp %s: bound class %s of type parameter %s was not found",
					ZSTR_VAL(display_name), ZSTR_VAL(param->bound_name),
					ZSTR_VAL(param->name));
			}
			return false;
		}

		if (param->bound_kind == ZEND_GENERIC_BOUND_IMPLEMENTS
				&& !(bound_ce->ce_flags & ZEND_ACC_INTERFACE)) {
			zend_throw_error(NULL,
				"Bound %s of type parameter %s on %s must be an interface "
				"(declared with \"implements\")",
				ZSTR_VAL(param->bound_name), ZSTR_VAL(param->name),
				ZSTR_VAL(template_ce->name));
			return false;
		}
		if (param->bound_kind == ZEND_GENERIC_BOUND_EXTENDS
				&& (bound_ce->ce_flags & (ZEND_ACC_INTERFACE | ZEND_ACC_TRAIT | ZEND_ACC_ENUM))) {
			zend_throw_error(NULL,
				"Bound %s of type parameter %s on %s must be a class "
				"(declared with \"extends\")",
				ZSTR_VAL(param->bound_name), ZSTR_VAL(param->name),
				ZSTR_VAL(template_ce->name));
			return false;
		}

		if (!instanceof_function(arg_ce, bound_ce)) {
			zend_throw_error(NULL,
				"%s does not satisfy the bound %s of type parameter %s on %s",
				ZSTR_VAL(arg_ce->name), ZSTR_VAL(bound_ce->name),
				ZSTR_VAL(param->name), ZSTR_VAL(template_ce->name));
			return false;
		}
	}
	return true;
}

static void zend_generics_collect_type_names(zend_type type, HashTable *candidates)
{
	if (ZEND_TYPE_HAS_LIST(type)) {
		const zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(type), list_type) {
			zend_generics_collect_type_names(*list_type, candidates);
		} ZEND_TYPE_LIST_FOREACH_END();
	} else if (ZEND_TYPE_HAS_NAME(type)) {
		zend_string *name = ZEND_TYPE_NAME(type);
		if (memchr(ZSTR_VAL(name), '<', ZSTR_LEN(name))) {
			zend_hash_add_empty_element(candidates, name);
		}
	}
}

static void zend_generics_collect_op_array(const zend_op_array *op_array, HashTable *candidates)
{
	if (op_array->type != ZEND_USER_FUNCTION) {
		return;
	}
	if (op_array->arg_info) {
		uint32_t total = op_array->num_args;
		const zend_arg_info *base = op_array->arg_info;
		if (op_array->fn_flags & ZEND_ACC_HAS_RETURN_TYPE) {
			base--;
			total++;
		}
		if (op_array->fn_flags & ZEND_ACC_VARIADIC) {
			total++;
		}
		for (uint32_t i = 0; i < total; i++) {
			zend_generics_collect_type_names(base[i].type, candidates);
		}
	}
	if (op_array->literals) {
		for (int i = 0; i < op_array->last_literal; i++) {
			const zval *zv = &op_array->literals[i];
			if (Z_TYPE_P(zv) == IS_STRING
					&& memchr(Z_STRVAL_P(zv), '<', Z_STRLEN_P(zv))) {
				zend_generic_name_slice base_slice;
				zend_generic_name_slice arg_slices[ZEND_GENERICS_MAX_ARGS];
				uint32_t num_args;
				if (zend_generics_parse_name(Z_STR_P(zv), &base_slice, arg_slices, &num_args)) {
					zend_hash_add_empty_element(candidates, Z_STR_P(zv));
				}
			}
		}
	}
	for (uint32_t i = 0; i < op_array->num_dynamic_func_defs; i++) {
		zend_generics_collect_op_array(op_array->dynamic_func_defs[i], candidates);
	}
}

/* Preload support: after preload_link, discover every generic instantiation
 * reachable from preloaded code (type positions, class-ref literals) and
 * stamp it, to a fixpoint so instantiations seed further instantiations.
 * Stamped classes sit in EG(class_table) and are persisted into SHM with
 * everything else, so requests start with the closed world fully stamped. */
ZEND_API void zend_generics_preload_stamp_all(void)
{
	HashTable candidates, attempted;
	bool stamped_any;

	zend_hash_init(&candidates, 64, NULL, NULL, 0);
	zend_hash_init(&attempted, 64, NULL, NULL, 0);

	do {
		stamped_any = false;
		zend_hash_clean(&candidates);

		zend_class_entry *scan_ce;
		ZEND_HASH_MAP_FOREACH_PTR(EG(class_table), scan_ce) {
			if (scan_ce->type != ZEND_USER_CLASS) {
				continue;
			}
			const zend_property_info *prop_info;
			ZEND_HASH_MAP_FOREACH_PTR(&scan_ce->properties_info, prop_info) {
				if (prop_info->ce == scan_ce) {
					zend_generics_collect_type_names(prop_info->type, &candidates);
					if (prop_info->hooks) {
						for (uint32_t i = 0; i < ZEND_PROPERTY_HOOK_COUNT; i++) {
							if (prop_info->hooks[i]) {
								zend_generics_collect_op_array(
									&prop_info->hooks[i]->op_array, &candidates);
							}
						}
					}
				}
			} ZEND_HASH_FOREACH_END();
			const zend_class_constant *c;
			ZEND_HASH_MAP_FOREACH_PTR(&scan_ce->constants_table, c) {
				if (c->ce == scan_ce) {
					zend_generics_collect_type_names(c->type, &candidates);
				}
			} ZEND_HASH_FOREACH_END();
			const zend_op_array *method;
			ZEND_HASH_MAP_FOREACH_PTR(&scan_ce->function_table, method) {
				if (method->scope == scan_ce) {
					zend_generics_collect_op_array(method, &candidates);
				}
			} ZEND_HASH_FOREACH_END();
		} ZEND_HASH_FOREACH_END();

		const zend_op_array *fn;
		ZEND_HASH_MAP_FOREACH_PTR(EG(function_table), fn) {
			zend_generics_collect_op_array(fn, &candidates);
		} ZEND_HASH_FOREACH_END();

		zend_string *candidate;
		ZEND_HASH_MAP_FOREACH_STR_KEY(&candidates, candidate) {
			if (!zend_hash_add_empty_element(&attempted, candidate)) {
				continue; /* already tried */
			}

			/* Only attempt names whose base is a known generic template, so
			 * false-positive string literals stay silent. */
			zend_generic_name_slice base_slice;
			zend_generic_name_slice arg_slices[ZEND_GENERICS_MAX_ARGS];
			uint32_t num_args;
			if (!zend_generics_parse_name(candidate, &base_slice, arg_slices, &num_args)) {
				continue;
			}
			zend_string *base_name = zend_string_init(base_slice.start, base_slice.len, 0);
			zend_class_entry *template_ce =
				zend_lookup_class_ex(base_name, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
			zend_string_release(base_name);
			if (!template_ce || !(template_ce->ce_flags2 & ZEND_ACC2_GENERIC_TEMPLATE)) {
				continue;
			}

			zend_string *lc_name = zend_string_tolower(candidate);
			if (!zend_hash_exists(EG(class_table), lc_name)) {
				if (zend_lookup_class_ex(candidate, NULL, 0)) {
					stamped_any = true;
				} else if (EG(exception)) {
					zend_error(E_WARNING,
						"Preloading could not stamp generic instantiation %s "
						"(it will be stamped, or fail, at runtime)",
						ZSTR_VAL(candidate));
					zend_clear_exception();
				}
			}
			zend_string_release(lc_name);
		} ZEND_HASH_FOREACH_END();
	} while (stamped_any);

	zend_hash_destroy(&candidates);
	zend_hash_destroy(&attempted);
}

ZEND_API zend_class_entry *zend_generics_stamp_instantiation(
		zend_string *name, zend_string *lc_name, bool use_autoload)
{
	zend_generic_name_slice base_slice;
	zend_generic_name_slice arg_slices[ZEND_GENERICS_MAX_ARGS];
	uint32_t num_args;

	if (!zend_generics_parse_name(name, &base_slice, arg_slices, &num_args)) {
		zend_throw_error(NULL, "Malformed generic class name \"%s\"", ZSTR_VAL(name));
		return NULL;
	}

	/* Locate the template. */
	zend_string *base_name = zend_string_init(base_slice.start, base_slice.len, 0);
	zend_class_entry *template_ce = zend_lookup_class_ex(base_name, NULL,
		use_autoload ? 0 : ZEND_FETCH_CLASS_NO_AUTOLOAD);
	zend_string_release(base_name);
	if (!template_ce) {
		return NULL; /* plain not-found; caller reports */
	}
	if (!(template_ce->ce_flags2 & ZEND_ACC2_GENERIC_TEMPLATE)) {
		zend_throw_error(NULL, "Class %s is not generic", ZSTR_VAL(template_ce->name));
		return NULL;
	}
	if (template_ce->generic_params->num_params != num_args) {
		zend_throw_error(NULL,
			"Generic class %s expects %u type argument%s, %u given",
			ZSTR_VAL(template_ce->name), template_ce->generic_params->num_params,
			template_ce->generic_params->num_params == 1 ? "" : "s", num_args);
		return NULL;
	}

	/* The template lookup may have autoloaded the defining file, which stamps
	 * eagerly-referenced instantiations; re-check the table. */
	zval *zv = zend_hash_find(EG(class_table), lc_name);
	if (zv) {
		return (zend_class_entry *) Z_PTR_P(zv);
	}

	/* Build the binding. */
	zend_generic_binding *binding = zend_arena_alloc(&CG(arena),
		sizeof(zend_generic_binding) + (num_args - 1) * sizeof(zend_type));
	binding->template_ce = template_ce;
	binding->num_args = num_args;
	for (uint32_t i = 0; i < num_args; i++) {
		uint32_t scalar_mask = zend_generics_scalar_mask(&arg_slices[i]);
		if (scalar_mask) {
			binding->args[i] = (zend_type) ZEND_TYPE_INIT_MASK(scalar_mask);
		} else {
			zend_string *arg_name = zend_new_interned_string(
				zend_string_init(arg_slices[i].start, arg_slices[i].len, 0));
			zend_alloc_ce_cache(arg_name);
			binding->args[i] = (zend_type) ZEND_TYPE_INIT_CLASS(arg_name, 0, 0);
		}
	}

	if (!zend_generics_check_bounds(template_ce, binding, name, use_autoload)) {
		/* Interning is a no-op at runtime under opcache, so the arg names
		 * are real refs; the binding won't outlive this failure. */
		for (uint32_t i = 0; i < num_args; i++) {
			if (ZEND_TYPE_HAS_NAME(binding->args[i])) {
				zend_string_release(ZEND_TYPE_NAME(binding->args[i]));
			}
		}
		return NULL;
	}

	zend_string *display = zend_new_interned_string(zend_string_copy(name));
	zend_string *lc_key = zend_new_interned_string(zend_string_copy(lc_name));
	zend_class_entry *ce = zend_generics_stamp_ce(template_ce, display, lc_key, binding);
	if (!ce) {
		zend_string_release(display);
		zend_string_release(lc_key);
		return NULL;
	}

	zv = zend_hash_add_ptr(EG(class_table), lc_key, ce);
	ZEND_ASSERT(zv && "mangled key cannot already be present");

	/* ce->name and the hash bucket hold their own refs. */
	zend_string_release(display);
	zend_string_release(lc_key);

	return ce;
}
