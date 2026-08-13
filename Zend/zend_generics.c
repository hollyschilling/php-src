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
#include "zend_smart_str.h"
#include "zend_exceptions.h"

#ifndef EMPTY_SWITCH_DEFAULT_CASE
# define EMPTY_SWITCH_DEFAULT_CASE() default: ZEND_UNREACHABLE();
#endif

#define ZEND_GENERICS_MAX_ARGS 64

ZEND_API void (*zend_generics_jit_clone_hook)(zend_op_array *op_array) = NULL;

typedef struct {
	const char *start;
	size_t len;
} zend_generic_name_slice;

/* Strict structural parse of a mangled name: BASE '<' ARG (',' ARG)* '>'
 * with no whitespace; args may nest. Returns false on any malformation.
 * When allow_spread is set (compiler-generated deferred refs only), an arg
 * may carry a "..." pack-expansion prefix; instantiation keys never may. */
static bool zend_generics_parse_name_ex(
		const zend_string *name, zend_generic_name_slice *base,
		zend_generic_name_slice *args, uint32_t *num_args, bool allow_spread)
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
		} else if (c == '.') {
			/* Accept exactly "..." as an arg prefix when spreads are legal. */
			if (!allow_spread || depth != 0 || p != arg_start
					|| p + 3 >= s + len || p[1] != '.' || p[2] != '.') {
				return false;
			}
			p += 2;
		} else if (!((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z')
				|| (c >= '0' && c <= '9') || c == '_' || c == '\\'
				|| (unsigned char) c >= 0x80 || c == ','
				/* composite (DNF) argument spellings: "a|(b&c)" */
				|| c == '|' || c == '&' || c == '(' || c == ')')) {
			return false;
		}
	}
	if (depth != 0 || count == 0) {
		return false;
	}
	*num_args = count;
	return true;
}

static zend_always_inline bool zend_generics_parse_name(
		const zend_string *name, zend_generic_name_slice *base,
		zend_generic_name_slice *args, uint32_t *num_args)
{
	return zend_generics_parse_name_ex(name, base, args, num_args, /* allow_spread */ false);
}

static uint32_t zend_generics_scalar_mask(const zend_generic_name_slice *slice)
{
	switch (slice->len) {
		case 3:
			if (zend_binary_strcasecmp(slice->start, 3, "int", 3) == 0) return MAY_BE_LONG;
			break;
		case 4:
			if (zend_binary_strcasecmp(slice->start, 4, "bool", 4) == 0) return MAY_BE_BOOL;
			if (zend_binary_strcasecmp(slice->start, 4, "null", 4) == 0) return MAY_BE_NULL;
			break;
		case 5:
			if (zend_binary_strcasecmp(slice->start, 5, "float", 5) == 0) return MAY_BE_DOUBLE;
			if (zend_binary_strcasecmp(slice->start, 5, "array", 5) == 0) return MAY_BE_ARRAY;
			break;
		case 6:
			if (zend_binary_strcasecmp(slice->start, 6, "string", 6) == 0) return MAY_BE_STRING;
			break;
	}
	return 0;
}

/* Does a mangled name carry composite (DNF) arguments? Such instantiations
 * are runtime-stamped only in this version (no preload persist). This is
 * deliberately conservative: nested composites count too. */
static zend_always_inline bool zend_generics_name_has_composite_args(const zend_string *name)
{
	return memchr(ZSTR_VAL(name), '|', ZSTR_LEN(name)) != NULL
		|| memchr(ZSTR_VAL(name), '&', ZSTR_LEN(name)) != NULL;
}

/* Is this argument slice itself composite ('a|b', 'a&b')? A '|' or '&'
 * inside a nested <...> belongs to the nested instantiation's own argument
 * list ('Box<int|string>' is a plain name at this level), so only depth-0
 * occurrences count. */
static bool zend_generics_slice_is_composite(const zend_generic_name_slice *slice)
{
	uint32_t depth = 0;
	const char *end = slice->start + slice->len;
	for (const char *p = slice->start; p < end; p++) {
		char c = *p;
		if (c == '<') {
			depth++;
		} else if (c == '>') {
			if (depth) depth--;
		} else if ((c == '|' || c == '&') && depth == 0) {
			return true;
		}
	}
	return false;
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

/* Maps a template param index onto its argument slice in a binding of
 * num_args arguments: params before the pack bind left-to-right, params
 * after it right-to-left, and the pack takes the middle slice (>= 1 by the
 * arity check). Identity mapping when the template declares no pack. */
static void zend_generics_param_arg_slice(
		const zend_generic_params *gp, uint32_t num_args,
		uint32_t param_idx, uint32_t *start, uint32_t *count)
{
	uint32_t pack = gp->pack_index;
	if (pack == (uint32_t) -1 || param_idx < pack) {
		*start = param_idx;
		*count = 1;
		return;
	}
	uint32_t pack_count = num_args - (gp->num_params - 1);
	if (param_idx == pack) {
		*start = pack;
		*count = pack_count;
	} else {
		*start = param_idx + pack_count - 1;
		*count = 1;
	}
}

/* Runtime form for the VM's type-param fetch: the opcode carries the param
 * index; with a pack in the template the binding index of a post-pack param
 * shifts by the instantiation's pack size. Packs themselves are compile-time
 * rejected in fetchable positions. */
ZEND_API uint32_t zend_generics_binding_arg_index(
		const zend_class_entry *scope_ce, uint32_t param_idx)
{
	const zend_generic_binding *binding = scope_ce->generic_binding;
	const zend_generic_params *gp = binding->template_ce->generic_params;
	uint32_t start, count;

	zend_generics_param_arg_slice(gp, binding->num_args, param_idx, &start, &count);
	ZEND_ASSERT(count == 1 && "packs cannot appear in fetchable positions");
	return start;
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

static zend_string *zend_generics_substitute_deferred_ref(
	zend_string *ref, const zend_class_entry *template_ce,
	const zend_generic_binding *binding);

/* Canonicalize a runtime-created type-name string (a binding argument or a
 * substituted composite) into a CE-cache-capable string, consuming the input.
 *
 * Since PHP 8.1 the arg/return type checks resolve classes exclusively
 * through the type-name STRING's CE cache (zend_fetch_ce_from_type); a name
 * without one pays a full lowercase-and-lookup on every check. Cache slots
 * can only be hosted by interned strings (the slot id lives in the refcount
 * field), and zend_alloc_ce_cache refuses permanent SHM strings at runtime
 * (the slot would die with the request while the string would not). Without
 * opcache, request interning makes everything work; under opcache no request
 * interning exists, so stamped signatures' names would stay uncacheable.
 *
 * Solution: keep a per-request table of canonical copies flagged
 * IS_STR_INTERNED by hand -- refcounting no-ops, the slot is safe, and the
 * string and its slot die together at request shutdown, exactly like
 * ordinary request-interned names. During preloading names must stay plain
 * (zend_persist_type allocates permanent slots for everything it persists,
 * and persistence asserts no runtime-flagged strings). */
static zend_string *zend_generics_request_type_name(zend_string *name)
{
	if (!ZSTR_IS_INTERNED(name)) {
		name = zend_new_interned_string(name);
	}
	if (ZSTR_IS_INTERNED(name)) {
		if (EXPECTED(ZSTR_HAS_CE_CACHE(name))) {
			return name;
		}
		if (!(GC_FLAGS(name) & IS_STR_PERMANENT)) {
			/* Genuinely request-interned (no opcache): slots are legal. */
			zend_alloc_ce_cache(name);
			return name;
		}
		/* Permanent SHM spelling without a slot: fall through to a
		 * request-lifetime capable copy. */
	}
	if (UNEXPECTED(CG(compiler_options) & ZEND_COMPILE_PRELOAD)) {
		return name;
	}

	HashTable *tab = EG(generics_type_names);
	if (!tab) {
		ALLOC_HASHTABLE(tab);
		zend_hash_init(tab, 8, NULL, NULL, 0);
		EG(generics_type_names) = tab;
	}
	zend_string *canon = zend_hash_find_ptr(tab, name);
	if (canon) {
		zend_string_release(name);
		return canon;
	}
	zend_string *copy = zend_string_init(ZSTR_VAL(name), ZSTR_LEN(name), 0);
	zend_string_hash_val(copy);
	GC_ADD_FLAGS(copy, IS_STR_INTERNED);
	zend_alloc_ce_cache(copy);
	zend_hash_add_new_ptr(tab, copy, copy);
	zend_string_release(name);
	return copy;
}

/* Register an owned substituted composite name on the binding; released with
 * the instance (zend_opcode.c). Deduped: closures re-substitute the same few
 * names per creation. The binding is logically mutable here even where the
 * substitution walk is const. */
static zend_string *zend_generics_binding_own_name(
		const zend_generic_binding *cbinding, zend_string *name)
{
	zend_generic_binding *binding = (zend_generic_binding *) cbinding;
	for (uint32_t i = 0; i < binding->num_owned_names; i++) {
		if (zend_string_equals(binding->owned_names[i], name)) {
			zend_string_release(name);
			return binding->owned_names[i];
		}
	}
	if (binding->num_owned_names == binding->owned_names_cap) {
		binding->owned_names_cap = binding->owned_names_cap ? binding->owned_names_cap * 2 : 4;
		binding->owned_names = erealloc(binding->owned_names,
			binding->owned_names_cap * sizeof(zend_string *));
	}
	binding->owned_names[binding->num_owned_names++] = name;
	return name;
}

/* Does a composite (mangled) name mention any of gp's parameters as a bare
 * argument label at ANY nesting depth ("C<T>", "Pair<Box<T>,int>")? A label
 * immediately followed by '<' is a base name, never a parameter; a label
 * containing '\\' is fully qualified, never a parameter. Accepts an optional
 * "..." spread prefix per argument. */
ZEND_API bool zend_generics_name_mentions_params(
		const zend_string *name, const zend_generic_params *gp)
{
	const char *p = memchr(ZSTR_VAL(name), '<', ZSTR_LEN(name));
	if (!p) {
		return false;
	}
	const char *end = ZSTR_VAL(name) + ZSTR_LEN(name);
	p++; /* skip the outer base name and its '<' */
	while (p < end) {
		char c = *p;
		if (c == ',' || c == '<' || c == '>' || c == '.'
				|| c == '|' || c == '&' || c == '(' || c == ')') {
			p++;
			continue;
		}
		const char *label = p;
		bool qualified = false;
		while (p < end && *p != ',' && *p != '<' && *p != '>'
				&& *p != '|' && *p != '&' && *p != '(' && *p != ')') {
			if (*p == '\\') {
				qualified = true;
			}
			p++;
		}
		if (p < end && *p == '<') {
			continue; /* base name of a nested reference */
		}
		if (!qualified) {
			for (uint32_t i = 0; i < gp->num_params; i++) {
				if (zend_binary_strcasecmp(label, p - label,
						ZSTR_VAL(gp->params[i].name), ZSTR_LEN(gp->params[i].name)) == 0) {
					return true;
				}
			}
		}
	}
	return false;
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
		zend_string *tname = ZEND_TYPE_NAME(*type);
		if (zend_generics_name_mentions_params(tname, template_ce->generic_params)) {
			/* Composite reference mentioning template params ("C<T>"):
			 * substitute at the string level. The binding owns the new name
			 * in the never-destroyed arg_info case; prop/const types release
			 * theirs through zend_type_release as usual. */
			zend_string *sub = zend_generics_request_type_name(
				zend_generics_substitute_deferred_ref(tname, template_ce, binding));
			if (!take_refs) {
				sub = zend_generics_binding_own_name(binding, sub);
			}
			uint32_t extra_mask2 = ZEND_TYPE_FULL_MASK(*type) & _ZEND_TYPE_MAY_BE_MASK;
			type->ptr = sub;
			type->type_mask = _ZEND_TYPE_NAME_BIT | extra_mask2;
			return true;
		}
		return false;
	}

	uint32_t arg_start, arg_count;
	zend_generics_param_arg_slice(template_ce->generic_params,
		binding->num_args, idx, &arg_start, &arg_count);
	ZEND_ASSERT(arg_count == 1 && "packs cannot appear in type positions");
	const zend_type arg = binding->args[arg_start];
	/* Preserve surrounding MAY_BE_* bits (e.g. nullability of "?T"). */
	uint32_t extra_mask = ZEND_TYPE_FULL_MASK(*type) & _ZEND_TYPE_MAY_BE_MASK;

	if (ZEND_TYPE_HAS_NAME(arg)) {
		type->ptr = take_refs
			? zend_string_copy(ZEND_TYPE_NAME(arg)) : ZEND_TYPE_NAME(arg);
		/* Preserve the argument's own builtin members too ("Foo|null"). */
		type->type_mask = _ZEND_TYPE_NAME_BIT | extra_mask
			| (ZEND_TYPE_FULL_MASK(arg) & _ZEND_TYPE_MAY_BE_MASK);
	} else if (ZEND_TYPE_HAS_LIST(arg)) {
		/* Composite (DNF) argument: arena-copy the list; names are owned by
		 * the binding for the never-destroyed arg_info case and addref'd for
		 * prop/const types, exactly like copy_ctor's conventions. */
		*type = arg;
		ZEND_TYPE_FULL_MASK(*type) |= extra_mask;
		zend_generics_type_copy_ctor(type, take_refs);
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
	if (!ZEND_TYPE_HAS_NAME(type)) {
		return false;
	}
	if (zend_generics_param_index(template_ce, ZEND_TYPE_NAME(type)) != (uint32_t) -1) {
		return true;
	}
	return zend_generics_name_mentions_params(
		ZEND_TYPE_NAME(type), template_ce->generic_params);
}

/* Append one class-shaped member to a rebuilt list, deduping plain names
 * case-insensitively. Sub-lists (intersections inside a union) are kept as
 * given. Ownership per take_refs: addref names when taking refs. */
static void zend_generics_list_append(
		zend_type *elems, uint32_t *n, zend_type m, bool take_refs)
{
	if (ZEND_TYPE_HAS_NAME(m)) {
		for (uint32_t i = 0; i < *n; i++) {
			if (ZEND_TYPE_HAS_NAME(elems[i])
					&& zend_string_equals_ci(ZEND_TYPE_NAME(elems[i]), ZEND_TYPE_NAME(m))) {
				return; /* duplicate member after substitution */
			}
		}
		if (take_refs) {
			zend_string_addref(ZEND_TYPE_NAME(m));
		}
	}
	elems[(*n)++] = m;
}

/* Deep-copy an intersection sub-list into the arena. */
static zend_type zend_generics_copy_sublist(zend_type src, bool take_refs)
{
	zend_type copy = src;
	zend_generics_type_copy_ctor(&copy, take_refs);
	return copy;
}

/* Rebuild a union/intersection type with this instantiation's arguments:
 * bare parameter members splice the argument in (builtin members fold into
 * the union's mask, a union argument's members splice, an intersection
 * argument nests), symbolic composite members ("Vec<T>") rewrite at the
 * string level, concrete members copy. Members dedupe after substitution.
 * Shape violations (a builtin or union argument inside an intersection)
 * throw; zend_generics_validate_substitution() runs the same rules before
 * any clone exists, so mid-clone failures cannot leak partial CEs. */
static bool zend_generics_substitute_list(
		zend_type *type, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, const zend_string *display_name,
		bool take_refs)
{
	const zend_type_list *old_list = ZEND_TYPE_LIST(*type);
	bool is_inter = ZEND_TYPE_IS_INTERSECTION(*type);
	uint32_t mask = ZEND_TYPE_FULL_MASK(*type) & _ZEND_TYPE_MAY_BE_MASK;
	uint32_t kind_bits = ZEND_TYPE_FULL_MASK(*type)
		& ~(_ZEND_TYPE_MAY_BE_MASK | _ZEND_TYPE_ARENA_BIT);
	uint32_t cap = (old_list->num_types + 1) * (ZEND_GENERICS_MAX_ARGS + 1);
	ALLOCA_FLAG(use_heap)
	zend_type *elems = do_alloca(cap * sizeof(zend_type), use_heap);
	uint32_t n = 0;
	const zend_type *m;

	ZEND_TYPE_LIST_FOREACH(old_list, m) {
		if (ZEND_TYPE_HAS_LIST(*m)) {
			/* nested intersection inside a union */
			zend_type sub = *m;
			if (zend_generics_type_uses_params(sub, template_ce)) {
				if (!zend_generics_substitute_list(&sub, template_ce, binding,
						display_name, take_refs)) {
					goto fail;
				}
			} else {
				sub = zend_generics_copy_sublist(*m, take_refs);
			}
			elems[n++] = sub;
			continue;
		}
		zend_string *name = ZEND_TYPE_NAME(*m);
		uint32_t idx = zend_generics_param_index(template_ce, name);
		if (idx != (uint32_t) -1) {
			uint32_t arg_start, arg_count;
			zend_generics_param_arg_slice(template_ce->generic_params,
				binding->num_args, idx, &arg_start, &arg_count);
			ZEND_ASSERT(arg_count == 1 && "packs cannot appear in type positions");
			const zend_type arg = binding->args[arg_start];
			uint32_t arg_mask = ZEND_TYPE_PURE_MASK(arg);

			if (is_inter) {
				/* intersections take class-shaped arguments only */
				if (arg_mask != 0 || (!ZEND_TYPE_HAS_NAME(arg)
						&& !(ZEND_TYPE_HAS_LIST(arg) && ZEND_TYPE_IS_INTERSECTION(arg)))) {
					zend_string *ts = zend_type_to_string(arg);
					zend_throw_error(NULL,
						"Cannot stamp %s: type argument %s for parameter %s "
						"cannot be used inside an intersection type",
						ZSTR_VAL(display_name), ZSTR_VAL(ts),
						ZSTR_VAL(template_ce->generic_params->params[idx].name));
					zend_string_release(ts);
					goto fail;
				}
				if (ZEND_TYPE_HAS_NAME(arg)) {
					zend_generics_list_append(elems, &n,
						(zend_type) ZEND_TYPE_INIT_CLASS(ZEND_TYPE_NAME(arg), 0, 0),
						take_refs);
				} else {
					const zend_type *ai;
					ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(arg), ai) {
						zend_generics_list_append(elems, &n,
							(zend_type) ZEND_TYPE_INIT_CLASS(ZEND_TYPE_NAME(*ai), 0, 0),
							take_refs);
					} ZEND_TYPE_LIST_FOREACH_END();
				}
				continue;
			}

			/* union member */
			mask |= arg_mask;
			if (ZEND_TYPE_HAS_NAME(arg)) {
				zend_generics_list_append(elems, &n,
					(zend_type) ZEND_TYPE_INIT_CLASS(ZEND_TYPE_NAME(arg), 0, 0),
					take_refs);
			} else if (ZEND_TYPE_HAS_LIST(arg)) {
				if (ZEND_TYPE_IS_INTERSECTION(arg)) {
					elems[n++] = zend_generics_copy_sublist(arg, take_refs);
				} else {
					const zend_type *ai;
					ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(arg), ai) {
						if (ZEND_TYPE_HAS_LIST(*ai)) {
							elems[n++] = zend_generics_copy_sublist(*ai, take_refs);
						} else {
							zend_generics_list_append(elems, &n,
								(zend_type) ZEND_TYPE_INIT_CLASS(ZEND_TYPE_NAME(*ai), 0, 0),
								take_refs);
						}
					} ZEND_TYPE_LIST_FOREACH_END();
				}
			}
			/* pure-mask argument: folded above */
			continue;
		}
		if (zend_generics_name_mentions_params(name, template_ce->generic_params)) {
			zend_string *sub = zend_generics_request_type_name(
				zend_generics_substitute_deferred_ref(name, template_ce, binding));
			if (!take_refs) {
				sub = zend_generics_binding_own_name(
					(zend_generic_binding *) binding, sub);
			}
			zend_type nm = (zend_type) ZEND_TYPE_INIT_CLASS(sub, 0, 0);
			/* ownership handled above; avoid the append addref for take_refs
			 * (the substituted ref is already ours) */
			for (uint32_t i = 0; i < n; i++) {
				if (ZEND_TYPE_HAS_NAME(elems[i])
						&& zend_string_equals_ci(ZEND_TYPE_NAME(elems[i]), sub)) {
					if (take_refs) {
						zend_string_release(sub);
					}
					nm.ptr = NULL;
					break;
				}
			}
			if (nm.ptr) {
				elems[n++] = nm;
			}
			continue;
		}
		zend_generics_list_append(elems, &n,
			(zend_type) ZEND_TYPE_INIT_CLASS(name, 0, 0), take_refs);
	} ZEND_TYPE_LIST_FOREACH_END();

	ZEND_ASSERT(n <= cap);
	if (n == 0) {
		ZEND_ASSERT(!is_inter && mask != 0);
		type->ptr = NULL;
		ZEND_TYPE_FULL_MASK(*type) = mask;
	} else if (n == 1 && !ZEND_TYPE_HAS_LIST(elems[0])) {
		type->ptr = ZEND_TYPE_NAME(elems[0]);
		ZEND_TYPE_FULL_MASK(*type) = _ZEND_TYPE_NAME_BIT | mask;
	} else {
		zend_type_list *nl = zend_arena_alloc(&CG(arena), ZEND_TYPE_LIST_SIZE(n));
		nl->num_types = n;
		memcpy(nl->types, elems, n * sizeof(zend_type));
		type->ptr = nl;
		ZEND_TYPE_FULL_MASK(*type) = kind_bits | _ZEND_TYPE_ARENA_BIT | mask;
	}
	free_alloca(elems, use_heap);
	return true;

fail:
	if (take_refs) {
		for (uint32_t i = 0; i < n; i++) {
			zend_generics_arg_release_names(elems[i]);
		}
	}
	free_alloca(elems, use_heap);
	return false;
}

/* Dry-run of the argument-dependent shape rules over one declared type:
 * a builtin, name+builtin, or union argument may not land inside an
 * intersection. Throws the same diagnostics substitution would. */
static bool zend_generics_validate_type_substitution(
		zend_type type, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, const zend_string *display_name)
{
	if (!ZEND_TYPE_HAS_LIST(type)) {
		return true;
	}
	bool is_inter = ZEND_TYPE_IS_INTERSECTION(type);
	const zend_type *m;
	ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(type), m) {
		if (ZEND_TYPE_HAS_LIST(*m)) {
			if (!zend_generics_validate_type_substitution(*m, template_ce,
					binding, display_name)) {
				return false;
			}
			continue;
		}
		if (!is_inter || !ZEND_TYPE_HAS_NAME(*m)) {
			continue;
		}
		uint32_t idx = zend_generics_param_index(template_ce, ZEND_TYPE_NAME(*m));
		if (idx == (uint32_t) -1) {
			continue;
		}
		uint32_t arg_start, arg_count;
		zend_generics_param_arg_slice(template_ce->generic_params,
			binding->num_args, idx, &arg_start, &arg_count);
		const zend_type arg = binding->args[arg_start];
		if (ZEND_TYPE_PURE_MASK(arg) != 0 || (!ZEND_TYPE_HAS_NAME(arg)
				&& !(ZEND_TYPE_HAS_LIST(arg) && ZEND_TYPE_IS_INTERSECTION(arg)))) {
			zend_string *ts = zend_type_to_string(arg);
			zend_throw_error(NULL,
				"Cannot stamp %s: type argument %s for parameter %s "
				"cannot be used inside an intersection type",
				ZSTR_VAL(display_name), ZSTR_VAL(ts),
				ZSTR_VAL(template_ce->generic_params->params[idx].name));
			zend_string_release(ts);
			return false;
		}
	} ZEND_TYPE_LIST_FOREACH_END();
	return true;
}

/* Walk every declared type of the template (methods, properties, constants)
 * BEFORE building the clone, so substitution cannot fail mid-stamp and
 * abandon a partially-built class entry. */
static bool zend_generics_validate_substitution(
		const zend_class_entry *template_ce, const zend_generic_binding *binding,
		const zend_string *display_name)
{
	zend_function *fn;
	ZEND_HASH_MAP_FOREACH_PTR(&template_ce->function_table, fn) {
		if (fn->type != ZEND_USER_FUNCTION || !fn->op_array.arg_info) {
			continue;
		}
		const zend_op_array *op = &fn->op_array;
		uint32_t total = op->num_args;
		const zend_arg_info *base = op->arg_info;
		if (op->fn_flags & ZEND_ACC_HAS_RETURN_TYPE) {
			base--;
			total++;
		}
		if (op->fn_flags & ZEND_ACC_VARIADIC) {
			total++;
		}
		for (uint32_t i = 0; i < total; i++) {
			if (!zend_generics_validate_type_substitution(base[i].type,
					template_ce, binding, display_name)) {
				return false;
			}
		}
	} ZEND_HASH_FOREACH_END();

	zend_property_info *prop;
	ZEND_HASH_MAP_FOREACH_PTR(&template_ce->properties_info, prop) {
		if (!zend_generics_validate_type_substitution(prop->type,
				template_ce, binding, display_name)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	zend_class_constant *c;
	ZEND_HASH_MAP_FOREACH_PTR(&template_ce->constants_table, c) {
		if (!zend_generics_validate_type_substitution(c->type,
				template_ce, binding, display_name)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();
	return true;
}

/* Copy `type` with substitution applied, owning all strings. Throws (and
 * returns false) on argument-dependent shape violations; the pre-clone
 * validation pass makes such failures unreachable during stamping. */
static bool zend_generics_substitute_type(
		zend_type *type, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, const zend_string *display_name,
		bool take_refs)
{
	if (ZEND_TYPE_HAS_LIST(*type)) {
		if (!zend_generics_type_uses_params(*type, template_ce)) {
			zend_generics_type_copy_ctor(type, take_refs);
			return true;
		}
		return zend_generics_substitute_list(type, template_ce, binding,
			display_name, take_refs);
	}
	if (!zend_generics_substitute_single(type, template_ce, binding, take_refs)) {
		zend_generics_type_copy_ctor(type, take_refs);
	}
	return true;
}

/* A substituted arg_info block is a memcpy of the template's, so it must take
 * references for the strings it copied: opcache releases the template's copy of
 * each while persisting a preloaded class, which would leave the instantiation
 * reading freed memory. zend_generics_release_substituted_arg_info() gives them
 * back. Both are no-ops for interned strings. */
static void zend_generics_arg_info_addref(zend_arg_info *entries, uint32_t count)
{
	for (uint32_t i = 0; i < count; i++) {
		if (entries[i].name) {
			zend_string_addref(entries[i].name);
		}
		if (entries[i].doc_comment) {
			zend_string_addref(entries[i].doc_comment);
		}
	}
}

ZEND_API void zend_generics_release_substituted_arg_info(zend_op_array *op_array)
{
	ZEND_ASSERT(op_array->fn_flags2 & ZEND_ACC2_GENERIC_SUBST_ARG_INFO);

	zend_arg_info *entries = op_array->arg_info;
	uint32_t count = op_array->num_args;

	if (op_array->fn_flags & ZEND_ACC_HAS_RETURN_TYPE) {
		entries--;
		count++;
	}
	if (op_array->fn_flags & ZEND_ACC_VARIADIC) {
		count++;
	}

	for (uint32_t i = 0; i < count; i++) {
		if (entries[i].name) {
			zend_string_release(entries[i].name);
		}
		if (entries[i].doc_comment) {
			zend_string_release(entries[i].doc_comment);
		}
		zend_type_release(entries[i].type, /* persistent */ false);
	}

	/* The block itself is arena memory; put back the template's real array,
	 * stashed one pointer before it, so the caller's teardown frees that. */
	op_array->arg_info = ((zend_arg_info **) entries)[-1];
	op_array->fn_flags2 &= ~ZEND_ACC2_GENERIC_SUBST_ARG_INFO;
}

/* Substitute a stamped scope's type arguments into a freshly created
 * closure's own op_array copy. Closure bodies (and their declared arg_info)
 * are shared with the template through dynamic_func_defs, so a signature
 * mentioning a type parameter would otherwise be enforced against a class
 * literally named "T". The substituted array uses the same hidden-original
 * discipline as method clones and is handled by destroy_op_array's restore. */
ZEND_API void zend_generics_substitute_closure_signature(
		zend_op_array *op_array, const zend_class_entry *scope)
{
	if (op_array->type != ZEND_USER_FUNCTION || !op_array->arg_info
			|| !scope || !scope->generic_binding
			|| !scope->generic_binding->template_ce->generic_params) {
		return;
	}
	const zend_class_entry *template_ce = scope->generic_binding->template_ce;

	uint32_t total = op_array->num_args;
	uint32_t has_ret = (op_array->fn_flags & ZEND_ACC_HAS_RETURN_TYPE) ? 1 : 0;
	zend_arg_info *base = op_array->arg_info - has_ret;
	total += has_ret;
	if (op_array->fn_flags & ZEND_ACC_VARIADIC) {
		total++;
	}

	bool uses_params = false;
	for (uint32_t i = 0; i < total; i++) {
		if (zend_generics_type_uses_params(base[i].type, template_ce)) {
			uses_params = true;
			break;
		}
	}
	if (!uses_params) {
		/* Includes fake closures over stamped methods: already substituted. */
		return;
	}

	char *block = zend_arena_alloc(&CG(arena),
		sizeof(zend_arg_info *) + total * sizeof(zend_arg_info));
	*(zend_arg_info **) block = op_array->arg_info;
	zend_arg_info *entries = (zend_arg_info *) (block + sizeof(zend_arg_info *));
	memcpy(entries, base, total * sizeof(zend_arg_info));
	for (uint32_t i = 0; i < total; i++) {
		if (!zend_generics_substitute_type(&entries[i].type, template_ce,
				scope->generic_binding, scope->name, /* take_refs */ true)) {
			/* Substitution threw (scalar arg inside a composite type); keep
			 * the original signature and let the Error propagate. Only the
			 * earlier types hold references yet. */
			while (i-- > 0) {
				zend_type_release(entries[i].type, /* persistent */ false);
			}
			return;
		}
	}
	zend_generics_arg_info_addref(entries, total);
	op_array->arg_info = entries + has_ret;
	op_array->fn_flags2 |= ZEND_ACC2_GENERIC_SUBST_ARG_INFO;
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
				if (!zend_generics_substitute_type(
						&entries[i].type, template_ce, binding, display_name,
						/* take_refs */ true)) {
					/* Only the earlier types hold references yet. */
					while (i-- > 0) {
						zend_type_release(entries[i].type, /* persistent */ false);
					}
					return NULL;
				}
			}
			zend_generics_arg_info_addref(entries, total);
			new_fn->arg_info = entries + has_ret;
			new_fn->fn_flags2 |= ZEND_ACC2_GENERIC_SUBST_ARG_INFO;
		}
	}
	if (UNEXPECTED(zend_generics_jit_clone_hook != NULL)) {
		zend_generics_jit_clone_hook(new_fn);
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
	/* The instance owns its tables, name and metadata refs, and must take the
	 * full destroy path even when the template came from SHM or the file
	 * cache (ZEND_ACC_CACHED would skip the name/metadata releases; harmless
	 * for interned names, but deferred-interface substitution creates
	 * runtime-allocated ones). */
	ce->ce_flags &= ~(ZEND_ACC_IMMUTABLE | ZEND_ACC_FILE_CACHED | ZEND_ACC_CACHED);
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
			zend_op_array *new_fn;
			if (UNEXPECTED(tpl_fn->type == ZEND_INTERNAL_FUNCTION)) {
				/* An internal function inherited into the template (e.g. the
				 * abstract getIterator prototype from IteratorAggregate, via a
				 * template interface extending it). No opcodes, no scope
				 * rebind, no substitution -- duplicate exactly as ordinary
				 * inheritance does (cf. zend_duplicate_internal_function). */
				new_fn = zend_arena_alloc(&CG(arena), sizeof(zend_internal_function));
				memcpy(new_fn, tpl_fn, sizeof(zend_internal_function));
				new_fn->fn_flags |= ZEND_ACC_ARENA_ALLOCATED;
				if (EXPECTED(new_fn->function_name)) {
					zend_string_addref(new_fn->function_name);
				}
			} else {
				new_fn = zend_generics_clone_method(
					tpl_fn, ce, template_ce, binding, display_name);
				if (!new_fn) {
					return NULL;
				}
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
			if (prop_info->ce != template_ce) {
				/* Inherited from a concrete parent: cannot mention type
				 * parameters, and its declaring scope must stay the parent
				 * (visibility from the parent's own scope -- e.g. the
				 * exception machinery writing Exception's protected
				 * properties -- depends on it). Share, exactly as ordinary
				 * inheritance does. */
				continue;
			}
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
	/* Rebuilt by the caller once the layout is final (a deferred-parent graft
	 * rebases property offsets after this clone). */
	ce->properties_info_table = NULL;

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
			if (c->ce != template_ce) {
				/* Inherited from a concrete parent: share (see properties). */
				continue;
			}
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

/* Iterator/ArrayAccess dispatch caches: the stamp memcpy shares the
 * template's structs, whose zend_function pointers reference the template's
 * methods. Internal dispatch (foreach, dim handlers) would then run with the
 * template's scope and fail protected/private access against members declared
 * on the instantiation. Rebuild them against the instance's function table,
 * keyed on which slots the TEMPLATE resolved (cf. the opcache persist fixup).
 * Runs after any parent graft and interface resolution: caches installed
 * fresh by interface_gets_implemented handlers during those steps belong to
 * slots the template did not have and are left untouched. */
static void zend_generics_rebuild_dispatch_ptrs(
		zend_class_entry *ce, const zend_class_entry *template_ce)
{
	if (template_ce->iterator_funcs_ptr) {
		const zend_class_iterator_funcs *tpl_funcs = template_ce->iterator_funcs_ptr;
		zend_class_iterator_funcs *funcs =
			zend_arena_alloc(&CG(arena), sizeof(zend_class_iterator_funcs));
		memset(funcs, 0, sizeof(zend_class_iterator_funcs));
		if (tpl_funcs->zf_new_iterator) {
			funcs->zf_new_iterator = zend_hash_str_find_ptr(
				&ce->function_table, "getiterator", sizeof("getiterator") - 1);
		}
		if (tpl_funcs->zf_rewind) {
			funcs->zf_rewind = zend_hash_str_find_ptr(
				&ce->function_table, "rewind", sizeof("rewind") - 1);
			funcs->zf_valid = zend_hash_str_find_ptr(
				&ce->function_table, "valid", sizeof("valid") - 1);
			funcs->zf_key = zend_hash_find_ptr(
				&ce->function_table, ZSTR_KNOWN(ZEND_STR_KEY));
			funcs->zf_current = zend_hash_str_find_ptr(
				&ce->function_table, "current", sizeof("current") - 1);
			funcs->zf_next = zend_hash_str_find_ptr(
				&ce->function_table, "next", sizeof("next") - 1);
		}
		ce->iterator_funcs_ptr = funcs;
	}
	if (template_ce->arrayaccess_funcs_ptr) {
		zend_class_arrayaccess_funcs *funcs =
			zend_arena_alloc(&CG(arena), sizeof(zend_class_arrayaccess_funcs));
		funcs->zf_offsetget = zend_hash_str_find_ptr(
			&ce->function_table, "offsetget", sizeof("offsetget") - 1);
		funcs->zf_offsetexists = zend_hash_str_find_ptr(
			&ce->function_table, "offsetexists", sizeof("offsetexists") - 1);
		funcs->zf_offsetset = zend_hash_str_find_ptr(
			&ce->function_table, "offsetset", sizeof("offsetset") - 1);
		funcs->zf_offsetunset = zend_hash_str_find_ptr(
			&ce->function_table, "offsetunset", sizeof("offsetunset") - 1);
		ce->arrayaccess_funcs_ptr = funcs;
	}
}

static bool zend_generics_build_composite_arg(
		const zend_generic_name_slice *slice, zend_type *out);

/* ---- Bound satisfaction (the 'T: <type>' form) -------------------------
 * A bound is stored as its canonical type string ("Countable",
 * "int|string", "A|(B&C)", "array"). An argument satisfies a bound when
 * every value the argument admits is admitted by the bound: union arguments
 * need every member to satisfy, intersection arguments satisfy through any
 * of their parts, builtin members satisfy only builtin bound members. */

/* Is concrete class `ce` a subtype of one bound member (a class/interface
 * name, or an intersection list requiring all parts)? 1/0; -1 with an
 * exception on lookup failure. */
static int zend_generics_class_satisfies_bound_member(
		zend_class_entry *ce, const zend_type bm, uint32_t lookup_flags,
		const zend_string *display_name, const zend_generic_param *param,
		bool quiet)
{
	if (ZEND_TYPE_HAS_LIST(bm)) {
		const zend_type *pt;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(bm), pt) {
			zend_class_entry *pce = zend_lookup_class_ex(ZEND_TYPE_NAME(*pt), NULL, lookup_flags);
			if (!pce) {
				if (quiet) {
					return 0;
				}
				if (!EG(exception)) {
					zend_throw_error(NULL,
						"Cannot stamp %s: bound class %s of type parameter %s was not found",
						ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(*pt)),
						ZSTR_VAL(param->name));
				}
				return -1;
			}
			if (!instanceof_function(ce, pce)) {
				return 0;
			}
		} ZEND_TYPE_LIST_FOREACH_END();
		return 1;
	}
	zend_class_entry *bce = zend_lookup_class_ex(ZEND_TYPE_NAME(bm), NULL, lookup_flags);
	if (!bce) {
		if (quiet) {
			return 0;
		}
		if (!EG(exception)) {
			zend_throw_error(NULL,
				"Cannot stamp %s: bound class %s of type parameter %s was not found",
				ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(bm)),
				ZSTR_VAL(param->name));
		}
		return -1;
	}
	return instanceof_function(ce, bce) ? 1 : 0;
}

/* Collect the bound's class-shaped members (names and intersection lists)
 * into `members`; builtin members live in the bound's pure mask. */
static uint32_t zend_generics_bound_class_members(
		const zend_type bound, const zend_type **members, uint32_t cap)
{
	if (ZEND_TYPE_HAS_LIST(bound) && !ZEND_TYPE_IS_INTERSECTION(bound)) {
		uint32_t n = 0;
		const zend_type *el;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(bound), el) {
			ZEND_ASSERT(n < cap);
			members[n++] = el;
		} ZEND_TYPE_LIST_FOREACH_END();
		return n;
	}
	if (ZEND_TYPE_HAS_NAME(bound)
			|| (ZEND_TYPE_HAS_LIST(bound) && ZEND_TYPE_IS_INTERSECTION(bound))) {
		/* borrow the caller's storage for the single member */
		return (uint32_t) -1; /* sentinel: the bound itself is the one member */
	}
	return 0;
}

/* One class-shaped argument atom (a class name, or an intersection list)
 * against the whole bound. */
static int zend_generics_atom_satisfies_bound(
		const zend_type atom, const zend_type bound, uint32_t lookup_flags,
		const zend_string *display_name, const zend_generic_param *param,
		bool quiet)
{
	const zend_type *members[ZEND_GENERICS_MAX_ARGS];
	uint32_t num_members = zend_generics_bound_class_members(bound, members, ZEND_GENERICS_MAX_ARGS);
	const zend_type *single[1] = { &bound };
	const zend_type **mem = members;
	if (num_members == (uint32_t) -1) {
		mem = single;
		num_members = 1;
	}

	if (ZEND_TYPE_HAS_LIST(atom) && ZEND_TYPE_IS_INTERSECTION(atom)) {
		/* A1&A2 <: bound iff some bound member accepts the intersection:
		 * a class member is satisfied by ANY part, an intersection member
		 * needs every part satisfied by SOME argument part. */
		zend_class_entry *parts[ZEND_GENERICS_MAX_ARGS];
		uint32_t num_parts = 0;
		const zend_type *ap;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(atom), ap) {
			zend_class_entry *pce = zend_lookup_class_ex(ZEND_TYPE_NAME(*ap), NULL, lookup_flags);
			if (!pce) {
				if (quiet) {
					return 0;
				}
				if (!EG(exception)) {
					zend_throw_error(NULL,
						"Cannot stamp %s: class %s for type parameter %s was not found",
						ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(*ap)),
						ZSTR_VAL(param->name));
				}
				return -1;
			}
			ZEND_ASSERT(num_parts < ZEND_GENERICS_MAX_ARGS);
			parts[num_parts++] = pce;
		} ZEND_TYPE_LIST_FOREACH_END();

		for (uint32_t m = 0; m < num_members; m++) {
			const zend_type bm = *mem[m];
			bool ok;
			if (ZEND_TYPE_HAS_LIST(bm)) {
				ok = true;
				const zend_type *bp;
				ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(bm), bp) {
					zend_class_entry *bce = zend_lookup_class_ex(ZEND_TYPE_NAME(*bp), NULL, lookup_flags);
					if (!bce) {
						if (quiet) {
							return 0;
						}
						if (!EG(exception)) {
							zend_throw_error(NULL,
								"Cannot stamp %s: bound class %s of type parameter %s was not found",
								ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(*bp)),
								ZSTR_VAL(param->name));
						}
						return -1;
					}
					bool any = false;
					for (uint32_t a = 0; a < num_parts; a++) {
						if (instanceof_function(parts[a], bce)) { any = true; break; }
					}
					if (!any) { ok = false; break; }
				} ZEND_TYPE_LIST_FOREACH_END();
			} else {
				zend_class_entry *bce = zend_lookup_class_ex(ZEND_TYPE_NAME(bm), NULL, lookup_flags);
				if (!bce) {
					if (quiet) {
						return 0;
					}
					if (!EG(exception)) {
						zend_throw_error(NULL,
							"Cannot stamp %s: bound class %s of type parameter %s was not found",
							ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(bm)),
							ZSTR_VAL(param->name));
					}
					return -1;
				}
				ok = false;
				for (uint32_t a = 0; a < num_parts; a++) {
					if (instanceof_function(parts[a], bce)) { ok = true; break; }
				}
			}
			if (ok) {
				return 1;
			}
		}
		return 0;
	}

	/* single class atom */
	zend_class_entry *ce = zend_lookup_class_ex(ZEND_TYPE_NAME(atom), NULL, lookup_flags);
	if (!ce) {
		if (quiet) {
			return 0;
		}
		if (!EG(exception)) {
			zend_throw_error(NULL,
				"Cannot stamp %s: class %s for type parameter %s was not found",
				ZSTR_VAL(display_name), ZSTR_VAL(ZEND_TYPE_NAME(atom)),
				ZSTR_VAL(param->name));
		}
		return -1;
	}
	for (uint32_t m = 0; m < num_members; m++) {
		int r = zend_generics_class_satisfies_bound_member(ce, *mem[m],
			lookup_flags, display_name, param, quiet);
		if (r != 0) {
			return r;
		}
	}
	return 0;
}

/* Whole argument against the whole bound. */
static int zend_generics_arg_satisfies_bound_type(
		const zend_type arg, const zend_type bound, uint32_t lookup_flags,
		const zend_string *display_name, const zend_generic_param *param,
		bool quiet)
{
	uint32_t bmask = ZEND_TYPE_PURE_MASK(bound);

	/* builtin members of the argument satisfy only builtin bound members */
	if (ZEND_TYPE_PURE_MASK(arg) & ~bmask) {
		return 0;
	}
	if (ZEND_TYPE_HAS_LIST(arg) && !ZEND_TYPE_IS_INTERSECTION(arg)) {
		const zend_type *el;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(arg), el) {
			int r = zend_generics_atom_satisfies_bound(*el, bound,
				lookup_flags, display_name, param, quiet);
			if (r != 1) {
				return r;
			}
		} ZEND_TYPE_LIST_FOREACH_END();
		return 1;
	}
	if (ZEND_TYPE_HAS_NAME(arg) || ZEND_TYPE_HAS_LIST(arg)) {
		return zend_generics_atom_satisfies_bound(arg, bound,
			lookup_flags, display_name, param, quiet);
	}
	return 1; /* pure-mask argument, already subset-checked above */
}

/* Substitute template parameters in a BOUND string ('Comparable<T>',
 * 'K', 'comparable<t>|x'). Unlike composite references, a bound may be a
 * bare name or DNF at top level, so the label walk starts at position 0
 * with no base-name skip: a label directly followed by '<' is a base name;
 * a qualified label is never a parameter; anything else is matched against
 * the template's parameters and replaced by the binding argument's
 * canonical rendering. Returns an owned string. */
static void zend_generics_append_binding_arg(smart_str *buf, const zend_type arg);

static zend_string *zend_generics_substitute_bound(
		const zend_string *bound, const zend_class_entry *template_ce,
		const zend_generic_binding *binding)
{
	const zend_generic_params *gp = template_ce->generic_params;
	smart_str buf = {0};
	const char *p = ZSTR_VAL(bound), *end = p + ZSTR_LEN(bound);

	while (p < end) {
		char c = *p;
		if (c == ',' || c == '<' || c == '>'
				|| c == '|' || c == '&' || c == '(' || c == ')') {
			smart_str_appendc(&buf, c);
			p++;
			continue;
		}
		const char *label = p;
		bool qualified = false;
		while (p < end && *p != ',' && *p != '<' && *p != '>'
				&& *p != '|' && *p != '&' && *p != '(' && *p != ')') {
			if (*p == '\\') {
				qualified = true;
			}
			p++;
		}
		if ((p < end && *p == '<') || qualified) {
			smart_str_appendl(&buf, label, p - label);
			continue;
		}
		uint32_t idx = (uint32_t) -1;
		for (uint32_t i = 0; i < gp->num_params; i++) {
			if (zend_binary_strcasecmp(label, p - label,
					ZSTR_VAL(gp->params[i].name), ZSTR_LEN(gp->params[i].name)) == 0) {
				idx = i;
				break;
			}
		}
		if (idx == (uint32_t) -1) {
			smart_str_appendl(&buf, label, p - label);
			continue;
		}
		uint32_t arg_start, arg_count;
		zend_generics_param_arg_slice(gp, binding->num_args, idx, &arg_start, &arg_count);
		ZEND_ASSERT(arg_count == 1 && "pack mentions in bounds are compile-time rejected");
		zend_generics_append_binding_arg(&buf, binding->args[arg_start]);
	}
	return smart_str_extract(&buf);
}

/* Parse a canonical bound string into a transient zend_type. Class-name refs
 * inside it are owned and must be released with
 * zend_generics_arg_release_names. */
static bool zend_generics_parse_bound_type(const zend_string *bound_name, zend_type *out)
{
	zend_generic_name_slice slice = { ZSTR_VAL(bound_name), ZSTR_LEN(bound_name) };
	uint32_t mask = zend_generics_scalar_mask(&slice);
	if (mask) {
		*out = (zend_type) ZEND_TYPE_INIT_MASK(mask);
		return true;
	}
	if (zend_generics_slice_is_composite(&slice)) {
		return zend_generics_build_composite_arg(&slice, out);
	}
	*out = (zend_type) ZEND_TYPE_INIT_CLASS(
		zend_generics_request_type_name(
			zend_string_init(slice.start, slice.len, 0)), 0, 0);
	return true;
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

		/* Generic bounds ('T: Comparable<T>', 'V: Box<K>') substitute this
		 * instantiation's own arguments before checking; concrete bounds
		 * pass through the walk verbatim. */
		zend_string *bound_src =
			zend_generics_substitute_bound(param->bound_name, template_ce, binding);

		zend_type bound_type;
		if (!zend_generics_parse_bound_type(bound_src, &bound_type)) {
			zend_throw_error(NULL,
				"Cannot stamp %s: malformed bound %s of type parameter %s",
				ZSTR_VAL(display_name), ZSTR_VAL(bound_src),
				ZSTR_VAL(param->name));
			zend_string_release(bound_src);
			return false;
		}

		bool failed = false;
		/* A pack bound applies to every argument in its slice. */
		uint32_t arg_start, arg_count;
		zend_generics_param_arg_slice(gp, binding->num_args, i, &arg_start, &arg_count);
		for (uint32_t j = arg_start; j < arg_start + arg_count; j++) {
			const zend_type arg = binding->args[j];
			int r = zend_generics_arg_satisfies_bound_type(arg, bound_type,
				lookup_flags, display_name, param, /* quiet */ false);
			if (r < 0) {
				failed = true;
				break;
			}
			if (r == 0) {
				zend_string *ts = zend_type_to_string(arg);
				zend_throw_error(NULL,
					"%s does not satisfy the bound %s of type parameter %s on %s",
					ZSTR_VAL(ts), ZSTR_VAL(bound_src),
					ZSTR_VAL(param->name), ZSTR_VAL(template_ce->name));
				zend_string_release(ts);
				failed = true;
				break;
			}
		}
		zend_generics_arg_release_names(bound_type);
		zend_string_release(bound_src);
		if (failed) {
			return false;
		}
	}
	return true;
}

static void zend_generics_collect_type_names(
		zend_type type, HashTable *candidates, const zend_generic_params *owner_gp)
{
	if (ZEND_TYPE_HAS_LIST(type)) {
		const zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(type), list_type) {
			zend_generics_collect_type_names(*list_type, candidates, owner_gp);
		} ZEND_TYPE_LIST_FOREACH_END();
	} else if (ZEND_TYPE_HAS_NAME(type)) {
		zend_string *name = ZEND_TYPE_NAME(type);
		if (memchr(ZSTR_VAL(name), '<', ZSTR_LEN(name))
				/* Symbolic template positions ("C<T>") are per-instantiation;
				 * only concrete names are preload-stampable. Composite (DNF)
				 * arguments are runtime-stamped in this version. */
				&& !zend_generics_name_has_composite_args(name)
				&& !(owner_gp && zend_generics_name_mentions_params(name, owner_gp))) {
			zend_hash_add_empty_element(candidates, name);
		}
	}
}

static void zend_generics_collect_op_array(
		const zend_op_array *op_array, HashTable *candidates,
		const zend_generic_params *owner_gp)
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
			zend_generics_collect_type_names(base[i].type, candidates, owner_gp);
		}
	}
	if (op_array->literals) {
		for (int i = 0; i < op_array->last_literal; i++) {
			const zval *zv = &op_array->literals[i];
			if (Z_TYPE_P(zv) == IS_STRING
					&& memchr(Z_STRVAL_P(zv), '<', Z_STRLEN_P(zv))
					&& !zend_generics_name_has_composite_args(Z_STR_P(zv))) {
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
		zend_generics_collect_op_array(op_array->dynamic_func_defs[i], candidates, owner_gp);
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
					zend_generics_collect_type_names(prop_info->type, &candidates,
						scan_ce->generic_params);
					if (prop_info->hooks) {
						for (uint32_t i = 0; i < ZEND_PROPERTY_HOOK_COUNT; i++) {
							if (prop_info->hooks[i]) {
								zend_generics_collect_op_array(
									&prop_info->hooks[i]->op_array, &candidates,
									scan_ce->generic_params);
							}
						}
					}
				}
			} ZEND_HASH_FOREACH_END();
			const zend_class_constant *c;
			ZEND_HASH_MAP_FOREACH_PTR(&scan_ce->constants_table, c) {
				if (c->ce == scan_ce) {
					zend_generics_collect_type_names(c->type, &candidates,
						scan_ce->generic_params);
				}
			} ZEND_HASH_FOREACH_END();
			const zend_op_array *method;
			ZEND_HASH_MAP_FOREACH_PTR(&scan_ce->function_table, method) {
				if (method->scope == scan_ce) {
					zend_generics_collect_op_array(method, &candidates,
						scan_ce->generic_params);
				}
			} ZEND_HASH_FOREACH_END();
		} ZEND_HASH_FOREACH_END();

		const zend_op_array *fn;
		ZEND_HASH_MAP_FOREACH_PTR(EG(function_table), fn) {
			zend_generics_collect_op_array(fn, &candidates, NULL);
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

static const char *zend_generics_scalar_type_name(uint32_t type_mask)
{
	switch (type_mask) {
		case MAY_BE_LONG:   return "int";
		case MAY_BE_DOUBLE: return "float";
		case MAY_BE_STRING: return "string";
		case MAY_BE_BOOL:   return "bool";
		case MAY_BE_ARRAY:  return "array";
		case MAY_BE_NULL:   return "null";
		EMPTY_SWITCH_DEFAULT_CASE()
	}
}

static int zend_generics_member_cmp(const void *a, const void *b)
{
	zend_string *sa = *(zend_string *const *) a;
	zend_string *sb = *(zend_string *const *) b;
	return zend_binary_strcasecmp(ZSTR_VAL(sa), ZSTR_LEN(sa), ZSTR_VAL(sb), ZSTR_LEN(sb));
}

/* Render a composite argument back to its canonical mangled spelling (the
 * same case-insensitive member sort the compiler applies), so substituted
 * composite references ("C<T>" with T = int|string) produce valid
 * instantiation keys. */
static void zend_generics_append_composite_arg(smart_str *buf, const zend_type arg)
{
	zend_string *elems[ZEND_GENERICS_MAX_ARGS + 6];
	uint32_t n = 0;
	char sep = '|';

	if (ZEND_TYPE_HAS_LIST(arg) && ZEND_TYPE_IS_INTERSECTION(arg)) {
		sep = '&';
		const zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(arg), list_type) {
			elems[n++] = zend_string_copy(ZEND_TYPE_NAME(*list_type));
		} ZEND_TYPE_LIST_FOREACH_END();
	} else {
		if (ZEND_TYPE_HAS_NAME(arg)) {
			elems[n++] = zend_string_copy(ZEND_TYPE_NAME(arg));
		} else if (ZEND_TYPE_HAS_LIST(arg)) {
			const zend_type *list_type;
			ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(arg), list_type) {
				if (ZEND_TYPE_HAS_LIST(*list_type)) {
					/* nested intersection member: "(a&b)" */
					smart_str tmp = {0};
					smart_str_appendc(&tmp, '(');
					const zend_type *it;
					bool first = true;
					ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(*list_type), it) {
						if (!first) {
							smart_str_appendc(&tmp, '&');
						}
						first = false;
						smart_str_append(&tmp, ZEND_TYPE_NAME(*it));
					} ZEND_TYPE_LIST_FOREACH_END();
					smart_str_appendc(&tmp, ')');
					elems[n++] = smart_str_extract(&tmp);
				} else {
					elems[n++] = zend_string_copy(ZEND_TYPE_NAME(*list_type));
				}
			} ZEND_TYPE_LIST_FOREACH_END();
		}
		uint32_t mask = ZEND_TYPE_PURE_MASK(arg);
		static const struct { uint32_t bit; const char *name; } scalars[] = {
			{ MAY_BE_ARRAY, "array" }, { MAY_BE_BOOL, "bool" },
			{ MAY_BE_DOUBLE, "float" }, { MAY_BE_LONG, "int" },
			{ MAY_BE_NULL, "null" }, { MAY_BE_STRING, "string" },
		};
		for (uint32_t i = 0; i < sizeof(scalars) / sizeof(scalars[0]); i++) {
			if (mask & scalars[i].bit) {
				elems[n++] = zend_string_init(scalars[i].name, strlen(scalars[i].name), 0);
			}
		}
	}

	qsort(elems, n, sizeof(zend_string *), zend_generics_member_cmp);
	for (uint32_t i = 0; i < n; i++) {
		if (i) {
			smart_str_appendc(buf, sep);
		}
		smart_str_append(buf, elems[i]);
		zend_string_release(elems[i]);
	}
}

static void zend_generics_append_binding_arg(smart_str *buf, const zend_type arg)
{
	uint32_t mask = ZEND_TYPE_PURE_MASK(arg);
	if (ZEND_TYPE_HAS_NAME(arg) && mask == 0) {
		smart_str_append(buf, ZEND_TYPE_NAME(arg));
	} else if (!ZEND_TYPE_HAS_NAME(arg) && !ZEND_TYPE_HAS_LIST(arg)
			&& (mask & (mask - 1)) == 0) {
		smart_str_appends(buf, zend_generics_scalar_type_name(mask));
	} else {
		zend_generics_append_composite_arg(buf, arg);
	}
}

/* Looks up a bare label in one space's parameter list; (uint32_t)-1 on miss. */
static uint32_t zend_generics_space_param_index(
		const zend_generic_params *gp, const char *name, size_t name_len)
{
	if (!gp) {
		return (uint32_t) -1;
	}
	for (uint32_t i = 0; i < gp->num_params; i++) {
		if (zend_binary_strcasecmp(name, name_len,
				ZSTR_VAL(gp->params[i].name), ZSTR_LEN(gp->params[i].name)) == 0) {
			return i;
		}
	}
	return (uint32_t) -1;
}

/* Rewrite a mangled composite name, replacing every parameter label -- at any
 * nesting depth ("Pair<Box<B>,C>") -- with its bound argument from the first
 * matching (params, binding) space; everything else is copied verbatim. Base
 * names (labels followed by '<') and qualified names are never parameters. A
 * "...Pack" spread splices in the pack's whole argument slice (the compiler
 * only emits spreads of the declaring template's own pack, at the top level
 * of deferred inheritance references). Labels matching no space stay as-is:
 * a class-space pass over a two-level signature leaves method-space
 * parameters symbolic for the later method-stamp pass, and vice versa.
 * Returns an owned, non-interned string. */
static zend_string *zend_generics_rewrite_composite(
		const char *val, size_t len,
		const zend_generic_params *gp1, const zend_generic_binding *b1,
		const zend_generic_params *gp2, const zend_generic_binding *b2)
{
	const char *end = val + len;
	const char *p = memchr(val, '<', len);
	ZEND_ASSERT(p && "composite names contain '<'");
	if (!b1) {
		gp1 = NULL;
	}
	if (!b2) {
		gp2 = NULL;
	}

	smart_str buf = {0};
	p++;
	smart_str_appendl(&buf, val, p - val); /* outer base name + '<' */

	while (p < end) {
		char c = *p;
		if (c == ',' || c == '<' || c == '>'
				|| c == '|' || c == '&' || c == '(' || c == ')') {
			smart_str_appendc(&buf, c);
			p++;
			continue;
		}
		bool spread = false;
		if (c == '.') {
			ZEND_ASSERT(p + 3 < end && p[1] == '.' && p[2] == '.'
				&& "spreads are compiler-generated");
			spread = true;
			p += 3;
		}
		const char *label = p;
		bool qualified = false;
		while (p < end && *p != ',' && *p != '<' && *p != '>'
				&& *p != '|' && *p != '&' && *p != '(' && *p != ')') {
			if (*p == '\\') {
				qualified = true;
			}
			p++;
		}
		if ((p < end && *p == '<') || qualified) {
			/* Base name of a nested reference, or fully qualified. */
			ZEND_ASSERT(!spread);
			smart_str_appendl(&buf, label, p - label);
			continue;
		}
		const zend_generic_params *gp = gp1;
		const zend_generic_binding *binding = b1;
		uint32_t idx = zend_generics_space_param_index(gp1, label, p - label);
		if (idx == (uint32_t) -1) {
			gp = gp2;
			binding = b2;
			idx = zend_generics_space_param_index(gp2, label, p - label);
		}
		if (idx == (uint32_t) -1) {
			ZEND_ASSERT(!spread);
			smart_str_appendl(&buf, label, p - label);
			continue;
		}
		uint32_t arg_start, arg_count;
		zend_generics_param_arg_slice(gp, binding->num_args, idx, &arg_start, &arg_count);
		if (spread) {
			ZEND_ASSERT(idx == gp->pack_index
				&& "only the declaring template's own pack can be spread");
			for (uint32_t j = 0; j < arg_count; j++) {
				if (j) {
					smart_str_appendc(&buf, ',');
				}
				zend_generics_append_binding_arg(&buf, binding->args[arg_start + j]);
			}
		} else {
			ZEND_ASSERT(arg_count == 1 && "bare pack refs are compile-time rejected");
			zend_generics_append_binding_arg(&buf, binding->args[arg_start]);
		}
	}
	return smart_str_extract(&buf);
}

/* ---- Post-substitution canonicalization ------------------------------
 * Compile-time canonicalization sorts DNF members, but label substitution
 * replaces members in place: "vec<null|t>" with T=Foo becomes
 * "vec<null|Foo>", while the directly-written spelling is "vec<Foo|null>".
 * Both must be ONE class-table key, so every substituted name is re-sorted
 * (and deduped: T=Foo turns "t|foo" into a duplicate) member-wise,
 * recursively through nested argument lists. */

static int zend_generics_canon_cmp(const void *a, const void *b)
{
	zend_string *sa = *(zend_string *const *) a;
	zend_string *sb = *(zend_string *const *) b;
	return zend_binary_strcasecmp(ZSTR_VAL(sa), ZSTR_LEN(sa), ZSTR_VAL(sb), ZSTR_LEN(sb));
}

static zend_string *zend_generics_canonicalize_slice(const char *s, size_t len);

/* One argument slice: canonicalize nested lists first, then sort/dedupe its
 * own top-level DNF members. Returns an owned string. */
static zend_string *zend_generics_canonicalize_arg(const char *s, size_t len)
{
	if (!memchr(s, '|', len) && !memchr(s, '&', len)) {
		return zend_generics_canonicalize_slice(s, len);
	}
	/* split top-level '|' (depth across <> and parens) */
	zend_string *members[ZEND_GENERICS_MAX_ARGS];
	uint32_t n = 0;
	uint32_t depth = 0;
	const char *end = s + len, *start = s;
	bool top_union = false;
	for (const char *p = s; p < end; p++) {
		if (*p == '<' || *p == '(') depth++;
		else if (*p == '>' || *p == ')') { if (depth) depth--; }
		else if (*p == '|' && depth == 0) top_union = true;
	}
	const char sep = top_union ? '|' : '&';
	depth = 0;
	for (const char *p = s; ; p++) {
		if (p == end || (*p == sep && depth == 0)) {
			size_t mlen = p - start;
			const char *m = start;
			zend_string *canon;
			if (top_union && mlen >= 2 && m[0] == '(' && m[mlen - 1] == ')') {
				zend_string *inner = zend_generics_canonicalize_arg(m + 1, mlen - 2);
				canon = zend_strpprintf(0, "(%s)", ZSTR_VAL(inner));
				zend_string_release(inner);
			} else if (!top_union) {
				/* intersection member: plain (possibly nested) name */
				canon = zend_generics_canonicalize_slice(m, mlen);
			} else {
				canon = zend_generics_canonicalize_arg(m, mlen);
			}
			bool dup = false;
			for (uint32_t i = 0; i < n; i++) {
				if (zend_string_equals_ci(members[i], canon)) {
					dup = true;
					break;
				}
			}
			if (dup || n >= ZEND_GENERICS_MAX_ARGS) {
				zend_string_release(canon);
			} else {
				members[n++] = canon;
			}
			if (p == end) break;
			start = p + 1;
		} else if (*p == '<' || *p == '(') {
			depth++;
		} else if (*p == '>' || *p == ')') {
			if (depth) depth--;
		}
	}
	if (n == 1) {
		/* post-substitution duplicates collapsed to a single member: the
		 * canonical spelling drops the composite (and its parens) */
		zend_string *only = members[0];
		if (ZSTR_LEN(only) >= 2 && ZSTR_VAL(only)[0] == '('
				&& ZSTR_VAL(only)[ZSTR_LEN(only) - 1] == ')') {
			zend_string *inner = zend_string_init(ZSTR_VAL(only) + 1, ZSTR_LEN(only) - 2, 0);
			zend_string_release(only);
			return inner;
		}
		return only;
	}
	qsort(members, n, sizeof(zend_string *), zend_generics_canon_cmp);
	smart_str buf = {0};
	for (uint32_t i = 0; i < n; i++) {
		if (i) smart_str_appendc(&buf, sep);
		smart_str_append(&buf, members[i]);
		zend_string_release(members[i]);
	}
	return smart_str_extract(&buf);
}

/* Whole slice: copy verbatim, rewriting each <...> argument list through
 * zend_generics_canonicalize_arg. */
static zend_string *zend_generics_canonicalize_slice(const char *s, size_t len)
{
	const char *lt = memchr(s, '<', len);
	if (!lt) {
		return zend_string_init(s, len, 0);
	}
	smart_str buf = {0};
	smart_str_appendl(&buf, s, lt - s + 1); /* base name + '<' */
	const char *end = s + len;
	const char *p = lt + 1, *start = p;
	uint32_t depth = 0;
	for (; ; p++) {
		if (p >= end) break;
		if ((*p == ',' || *p == '>') && depth == 0) {
			zend_string *arg = zend_generics_canonicalize_arg(start, p - start);
			smart_str_append(&buf, arg);
			zend_string_release(arg);
			smart_str_appendc(&buf, *p);
			if (*p == '>') {
				p++;
				/* trailing text after the list (should not occur) */
				if (p < end) smart_str_appendl(&buf, p, end - p);
				break;
			}
			start = p + 1;
		} else if (*p == '<' || *p == '(') {
			depth++;
		} else if (*p == '>' || *p == ')') {
			depth--;
		}
	}
	return smart_str_extract(&buf);
}

/* Canonicalize a substituted mangled name; consumes the input ref. */
static zend_string *zend_generics_canonicalize_name(zend_string *name)
{
	if (!memchr(ZSTR_VAL(name), '|', ZSTR_LEN(name))
			&& !memchr(ZSTR_VAL(name), '&', ZSTR_LEN(name))) {
		return name;
	}
	zend_string *canon = zend_generics_canonicalize_slice(ZSTR_VAL(name), ZSTR_LEN(name));
	zend_string_release(name);
	return canon;
}

/* Substitute this instantiation's type arguments into a deferred inheritance
 * reference such as "App\Collection<T>" or "App\Merger<...Ts>" (bare args
 * only there, so substitution depth cannot grow across eager stamp chains),
 * or into a signature/body composite reference, where parameters may sit at
 * any nesting depth ("Pair<Box<T>,int>"). Returns an owned string. */
static zend_string *zend_generics_substitute_deferred_ref(
		zend_string *ref, const zend_class_entry *template_ce,
		const zend_generic_binding *binding)
{
	/* Re-canonicalize after substitution: replacing a member label can
	 * violate the sorted-member identity ("vec<null|t>" with T=Foo must
	 * become "Vec<Foo|null>", not "Vec<null|Foo>") or create duplicates. */
	return zend_generics_canonicalize_name(
		zend_generics_rewrite_composite(ZSTR_VAL(ref), ZSTR_LEN(ref),
			template_ce->generic_params, binding, NULL, NULL));
}

static bool zend_generics_ce_implements_ptr(
		const zend_class_entry *ce, const zend_class_entry *iface)
{
	for (uint32_t i = 0; i < ce->num_interfaces; i++) {
		if (ce->interfaces[i] == iface) {
			return true;
		}
	}
	return false;
}

/* Resolve the template's param-dependent implements/extends-interface
 * references for this instantiation: substitute, stamp the interface
 * instantiation, and add the edges (flattened, deduped -- deduping ourselves
 * because zend_do_implement_interface treats duplicates as fatal, and a
 * diamond via a concrete interface is legitimate here). Method satisfaction
 * and variance are checked per instantiation by the ordinary machinery. */
static bool zend_generics_resolve_deferred_interfaces(
		zend_class_entry *ce, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, bool use_autoload)
{
	const zend_generic_params *gp = template_ce->generic_params;

	/* Set before appending: destroy_zend_class reads the interfaces union by
	 * this flag, and a failure below abandons a partially-edged instance. */
	ce->ce_flags |= ZEND_ACC_RESOLVED_INTERFACES;

	for (uint32_t i = 0; i < gp->num_deferred_interfaces; i++) {
		zend_string *sub = zend_generics_substitute_deferred_ref(
			gp->deferred_interfaces[i], template_ce, binding);
		zend_class_entry *iface = zend_lookup_class_ex(sub, NULL,
			use_autoload ? 0 : ZEND_FETCH_CLASS_NO_AUTOLOAD);
		if (!iface) {
			if (!EG(exception)) {
				zend_throw_error(NULL, "Interface %s required by %s was not found",
					ZSTR_VAL(sub), ZSTR_VAL(ce->name));
			}
			zend_string_release(sub);
			return false;
		}
		if (!(iface->ce_flags & ZEND_ACC_INTERFACE)) {
			zend_throw_error(NULL, "%s cannot implement %s - it is not an interface",
				ZSTR_VAL(ce->name), ZSTR_VAL(sub));
			zend_string_release(sub);
			return false;
		}
		zend_string_release(sub);

		/* Flatten: the interface's transitive interfaces first, then itself. */
		for (uint32_t j = 0; j < iface->num_interfaces; j++) {
			if (!zend_generics_ce_implements_ptr(ce, iface->interfaces[j])) {
				zend_do_implement_interface(ce, iface->interfaces[j]);
				if (UNEXPECTED(EG(exception))) {
					return false;
				}
			}
		}
		if (!zend_generics_ce_implements_ptr(ce, iface)) {
			zend_do_implement_interface(ce, iface);
			if (UNEXPECTED(EG(exception))) {
				return false;
			}
		}
	}

	if ((ce->ce_flags & ZEND_ACC_IMPLICIT_ABSTRACT_CLASS)
			&& !(ce->ce_flags & (ZEND_ACC_INTERFACE | ZEND_ACC_EXPLICIT_ABSTRACT_CLASS))
			&& !(template_ce->ce_flags & ZEND_ACC_IMPLICIT_ABSTRACT_CLASS)) {
		/* The template is missing interface members: report which. */
		zend_verify_abstract_class(ce);
		if (UNEXPECTED(EG(exception))) {
			return false;
		}
	}
	return true;
}

/* Graft the deferred (param-dependent) parent under a freshly stamped
 * instantiation: substitute this binding's arguments into the template's
 * deferred extends reference, stamp/locate the parent instantiation, and run
 * ordinary inheritance against it. The template linked parentless, so the
 * clone holds only own members; the graft merges parent members, inherits
 * the constructor, and runs the per-instantiation compatibility checks. */
static bool zend_generics_graft_parent(
		zend_class_entry *ce, const zend_class_entry *template_ce,
		const zend_generic_binding *binding, bool use_autoload)
{
	zend_string *sub = zend_generics_substitute_deferred_ref(
		template_ce->generic_params->deferred_parent, template_ce, binding);
	zend_class_entry *parent = zend_lookup_class_ex(sub, NULL,
		use_autoload ? 0 : ZEND_FETCH_CLASS_NO_AUTOLOAD);
	if (!parent) {
		if (!EG(exception)) {
			zend_throw_error(NULL, "Cannot stamp %s: parent class %s was not found",
				ZSTR_VAL(ce->name), ZSTR_VAL(sub));
		}
		zend_string_release(sub);
		return false;
	}
	/* Keep the common shape failures catchable; ordinary linking reports the
	 * same conditions as fatals. */
	if (parent->ce_flags & (ZEND_ACC_INTERFACE | ZEND_ACC_TRAIT | ZEND_ACC_ENUM)) {
		zend_throw_error(NULL, "Cannot stamp %s: cannot extend %s %s",
			ZSTR_VAL(ce->name),
			(parent->ce_flags & ZEND_ACC_INTERFACE) ? "interface"
				: (parent->ce_flags & ZEND_ACC_TRAIT) ? "trait" : "enum",
			ZSTR_VAL(parent->name));
		zend_string_release(sub);
		return false;
	}
	if (parent->ce_flags & ZEND_ACC_FINAL) {
		zend_throw_error(NULL, "Cannot stamp %s: cannot extend final class %s",
			ZSTR_VAL(ce->name), ZSTR_VAL(parent->name));
		zend_string_release(sub);
		return false;
	}
	zend_string_release(sub);

	zend_do_inheritance_ex(ce, parent, /* checked */ false);
	if (UNEXPECTED(EG(exception))) {
		return false;
	}

	/* Parent interfaces are merged by the link path, not by
	 * zend_do_inheritance_ex; append the flattened, deduped set here. */
	ce->ce_flags |= ZEND_ACC_RESOLVED_INTERFACES;
	for (uint32_t i = 0; i < parent->num_interfaces; i++) {
		if (!zend_generics_ce_implements_ptr(ce, parent->interfaces[i])) {
			zend_do_implement_interface(ce, parent->interfaces[i]);
			if (UNEXPECTED(EG(exception))) {
				return false;
			}
		}
	}
	return true;
}

/* Release every class-name reference a binding argument holds, including
 * names inside composite type lists (the list buffers are arena-allocated
 * and die with the request). */
ZEND_API void zend_generics_arg_release_names(zend_type arg)
{
	if (ZEND_TYPE_HAS_LIST(arg)) {
		const zend_type *list_type;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(arg), list_type) {
			zend_generics_arg_release_names(*list_type);
		} ZEND_TYPE_LIST_FOREACH_END();
	} else if (ZEND_TYPE_HAS_NAME(arg)) {
		zend_string_release_ex(ZEND_TYPE_NAME(arg), 0);
	}
}

/* Build the type for a pure-intersection argument slice ("a&b<x>&c"):
 * every member must be a class name. */
static bool zend_generics_build_intersection_arg(
		const zend_generic_name_slice *slice, zend_type *out)
{
	zend_type elems[ZEND_GENERICS_MAX_ARGS];
	uint32_t num_elems = 0;
	uint32_t depth = 0;
	const char *end = slice->start + slice->len;
	const char *start = slice->start;

	for (const char *q = slice->start; ; q++) {
		if (q == end || (*q == '&' && depth == 0)) {
			if (q == start || num_elems >= ZEND_GENERICS_MAX_ARGS) {
				goto fail;
			}
			zend_generic_name_slice m = { start, (size_t) (q - start) };
			if (zend_generics_scalar_mask(&m) != 0
					|| zend_generics_slice_is_composite(&m)) {
				goto fail; /* only class types intersect */
			}
			zend_string *nm = zend_generics_request_type_name(
				zend_string_init(m.start, m.len, 0));
			elems[num_elems] = (zend_type) ZEND_TYPE_INIT_CLASS(nm, 0, 0);
			num_elems++;
			if (q == end) {
				break;
			}
			start = q + 1;
		} else if (*q == '<') {
			depth++;
		} else if (*q == '>') {
			if (!depth) goto fail;
			depth--;
		} else if (*q == '(' || *q == ')') {
			if (!depth) goto fail;
		}
	}
	if (depth != 0 || num_elems < 2) {
		goto fail;
	}
	{
		zend_type_list *l = zend_arena_alloc(&CG(arena), ZEND_TYPE_LIST_SIZE(num_elems));
		l->num_types = num_elems;
		memcpy(l->types, elems, num_elems * sizeof(zend_type));
		*out = (zend_type) ZEND_TYPE_INIT_INTERSECTION(l, _ZEND_TYPE_ARENA_BIT);
	}
	return true;
fail:
	for (uint32_t i = 0; i < num_elems; i++) {
		zend_generics_arg_release_names(elems[i]);
	}
	return false;
}

/* Build the zend_type for a composite (DNF) argument slice ("a|(b&c)",
 * "int|string", "foo|null", "a&b"). Scalar/array/null members become mask
 * bits, class members request-canonical name types; multi-class unions and
 * intersections become arena-allocated type lists. Instances stamped from
 * composite arguments are runtime-local in this version (preload refuses
 * them upstream). Returns false on malformed input; the caller throws. */
static bool zend_generics_build_composite_arg(
		const zend_generic_name_slice *slice, zend_type *out)
{
	zend_generic_name_slice members[ZEND_GENERICS_MAX_ARGS];
	uint32_t num_members = 0;
	{
		uint32_t depth = 0;
		const char *end = slice->start + slice->len;
		const char *start = slice->start;
		for (const char *q = slice->start; ; q++) {
			if (q == end || (*q == '|' && depth == 0)) {
				if (q == start || num_members >= ZEND_GENERICS_MAX_ARGS) {
					return false;
				}
				members[num_members].start = start;
				members[num_members].len = q - start;
				num_members++;
				if (q == end) {
					break;
				}
				start = q + 1;
			} else if (*q == '<' || *q == '(') {
				depth++;
			} else if (*q == '>' || *q == ')') {
				if (!depth) return false;
				depth--;
			}
		}
		if (depth != 0) {
			return false;
		}
	}

	/* Whole argument is one bare intersection ("a&b"). */
	if (num_members == 1) {
		return zend_generics_build_intersection_arg(&members[0], out);
	}

	uint32_t mask = 0;
	zend_type elems[ZEND_GENERICS_MAX_ARGS];
	uint32_t num_elems = 0;

	for (uint32_t i = 0; i < num_members; i++) {
		zend_generic_name_slice m = members[i];
		if (m.len >= 2 && m.start[0] == '(') {
			if (m.start[m.len - 1] != ')') {
				goto fail;
			}
			m.start++;
			m.len -= 2;
			if (!zend_generics_build_intersection_arg(&m, &elems[num_elems])) {
				goto fail;
			}
			num_elems++;
			continue;
		}
		if (zend_generics_slice_is_composite(&m)) {
			goto fail; /* intersections inside unions must be parenthesized */
		}
		uint32_t sm = zend_generics_scalar_mask(&m);
		if (sm) {
			mask |= sm;
			continue;
		}
		zend_string *nm = zend_generics_request_type_name(
			zend_string_init(m.start, m.len, 0));
		elems[num_elems] = (zend_type) ZEND_TYPE_INIT_CLASS(nm, 0, 0);
		num_elems++;
	}

	if (num_elems == 0) {
		if (mask == 0 || mask == MAY_BE_NULL) {
			return false;
		}
		*out = (zend_type) ZEND_TYPE_INIT_MASK(mask);
		return true;
	}
	if (num_elems == 1 && !ZEND_TYPE_HAS_LIST(elems[0])) {
		*out = elems[0];
		ZEND_TYPE_FULL_MASK(*out) |= mask;
		return true;
	}
	{
		zend_type_list *l = zend_arena_alloc(&CG(arena), ZEND_TYPE_LIST_SIZE(num_elems));
		l->num_types = num_elems;
		memcpy(l->types, elems, num_elems * sizeof(zend_type));
		*out = (zend_type) ZEND_TYPE_INIT_UNION(l, _ZEND_TYPE_ARENA_BIT | mask);
	}
	return true;
fail:
	for (uint32_t i = 0; i < num_elems; i++) {
		zend_generics_arg_release_names(elems[i]);
	}
	return false;
}

static zend_class_entry *zend_generics_stamp_instantiation_impl(
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
	if (template_ce->generic_params->pack_index != (uint32_t) -1) {
		/* The pack binds at least one argument; every other param exactly
		 * one, so num_params doubles as the minimum arity. */
		if (num_args < template_ce->generic_params->num_params) {
			zend_throw_error(NULL,
				"Generic class %s expects at least %u type argument%s, %u given",
				ZSTR_VAL(template_ce->name), template_ce->generic_params->num_params,
				template_ce->generic_params->num_params == 1 ? "" : "s", num_args);
			return NULL;
		}
	} else if (template_ce->generic_params->num_params != num_args) {
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
	binding->num_owned_names = 0;
	binding->owned_names_cap = 0;
	binding->owned_names = NULL;
	for (uint32_t i = 0; i < num_args; i++) {
		const zend_generic_name_slice *slice = &arg_slices[i];
		uint32_t scalar_mask = zend_generics_scalar_mask(slice);
		if (scalar_mask) {
			if (UNEXPECTED(scalar_mask == MAY_BE_NULL)) {
				/* null is a union member, never a standalone argument. */
				goto malformed_arg;
			}
			binding->args[i] = (zend_type) ZEND_TYPE_INIT_MASK(scalar_mask);
		} else if (zend_generics_slice_is_composite(slice)) {
			if (UNEXPECTED(CG(compiler_options) & ZEND_COMPILE_PRELOAD)) {
				zend_throw_error(NULL,
					"Cannot stamp %s during preloading: composite type arguments "
					"are runtime-stamped in this version", ZSTR_VAL(name));
				goto release_built_args;
			}
			if (!zend_generics_build_composite_arg(slice, &binding->args[i])) {
				goto malformed_arg;
			}
		} else {
			zend_string *arg_name = zend_generics_request_type_name(
				zend_string_init(slice->start, slice->len, 0));
			binding->args[i] = (zend_type) ZEND_TYPE_INIT_CLASS(arg_name, 0, 0);
		}
		continue;
malformed_arg:
		zend_throw_error(NULL, "Malformed generic class name \"%s\"", ZSTR_VAL(name));
release_built_args:
		for (uint32_t j = 0; j < i; j++) {
			zend_generics_arg_release_names(binding->args[j]);
		}
		return NULL;
	}

	if (!zend_generics_check_variance_deep(template_ce, use_autoload)
			|| !zend_generics_check_bounds(template_ce, binding, name, use_autoload)) {
		/* Interning is a no-op at runtime under opcache, so the arg names
		 * are real refs; the binding won't outlive this failure. */
		for (uint32_t i = 0; i < num_args; i++) {
			zend_generics_arg_release_names(binding->args[i]);
		}
		return NULL;
	}

	if (!zend_generics_validate_substitution(template_ce, binding, name)) {
		for (uint32_t i = 0; i < num_args; i++) {
			zend_generics_arg_release_names(binding->args[i]);
		}
		return NULL;
	}

	/* Plain refs, deliberately not interned: in-place request interning would
	 * flag the caller's allocation and its cleanup is interning-handler
	 * specific (runtime-built names arrive here via deferred-interface
	 * substitution). Preload persist interns names and keys itself. */
	zend_string *display = zend_string_copy(name);
	zend_string *lc_key = zend_string_copy(lc_name);
	zend_class_entry *ce = zend_generics_stamp_ce(template_ce, display, lc_key, binding);
	if (!ce) {
		zend_string_release(display);
		zend_string_release(lc_key);
		return NULL;
	}

	const zend_generic_params *gp = template_ce->generic_params;

	/* The clone is fully built at this point, so any failure below destroys
	 * it properly instead of leaking it with the request arena. */
	if (gp->deferred_parent
			&& !zend_generics_graft_parent(ce, template_ce, binding, use_autoload)) {
		goto fail_destroy;
	}

	if (gp->num_deferred_interfaces
			&& !zend_generics_resolve_deferred_interfaces(ce, template_ce, binding, use_autoload)) {
		goto fail_destroy;
	}

	if (gp->deferred_parent || gp->num_deferred_interfaces) {
		/* Method-compatibility checks against the grafted parent (or the
		 * resolved interfaces) may have recorded delayed obligations for
		 * types that were not loaded yet; settle them now, as the runtime
		 * link path does. */
		zend_resolve_delayed_variance_obligations_ex(ce);
		if (UNEXPECTED(EG(exception))) {
			goto fail_destroy;
		}
	}

	if (gp->deferred_parent) {
		zend_inheritance_check_override(ce);
		if ((ce->ce_flags & ZEND_ACC_IMPLICIT_ABSTRACT_CLASS)
				&& !(ce->ce_flags & (ZEND_ACC_INTERFACE | ZEND_ACC_EXPLICIT_ABSTRACT_CLASS))
				&& !(template_ce->ce_flags & ZEND_ACC_IMPLICIT_ABSTRACT_CLASS)) {
			/* The grafted parent left abstract methods unimplemented. */
			zend_verify_abstract_class(ce);
			if (UNEXPECTED(EG(exception))) {
				goto fail_destroy;
			}
		}
	}

	/* Final layout is settled (a graft rebases property offsets and adds
	 * parent members); build the derived tables. */
	zend_generics_rebuild_dispatch_ptrs(ce, template_ce);
	if (ce->default_properties_count) {
		zend_build_properties_info_table(ce);
	}

	zv = zend_hash_add_ptr(EG(class_table), lc_key, ce);
	ZEND_ASSERT(zv && "mangled key cannot already be present");

	/* ce->name and the hash bucket hold their own refs. */
	zend_string_release(display);
	zend_string_release(lc_key);

	return ce;

fail_destroy:;
	zval zv_ce;
	ZVAL_PTR(&zv_ce, ce);
	destroy_zend_class(&zv_ce);
	zend_string_release(display);
	zend_string_release(lc_key);
	return NULL;
}

ZEND_API zend_class_entry *zend_generics_stamp_instantiation(
		zend_string *name, zend_string *lc_name, bool use_autoload)
{
	if (!EG(generics_stamping)) {
		ALLOC_HASHTABLE(EG(generics_stamping));
		zend_hash_init(EG(generics_stamping), 8, NULL, NULL, 0);
	}
	if (zend_hash_add_empty_element(EG(generics_stamping), lc_name) == NULL) {
		zend_throw_error(NULL, "Circular generic instantiation involving %s", ZSTR_VAL(name));
		return NULL;
	}

	zend_class_entry *ce = zend_generics_stamp_instantiation_impl(name, lc_name, use_autoload);

	zend_hash_del(EG(generics_stamping), lc_name);
	return ce;
}

/* Resolve a compiler-emitted symbolic generic class reference ("Vec<T>" or
 * a bare "T"-shaped composite from a template body) against the executing
 * scope's binding. Returns an owned string, or NULL with an exception. */
/* ---- Variance ----------------------------------------------------------
 * Declared on interface type parameters only ('in' / 'out', stored in
 * bound_kind). Soundness comes from the positional discipline checked at
 * declaration; the runtime side only adds subtype edges between stamped
 * instantiations of one variant template. */

static zend_always_inline uint32_t zend_generics_param_variance(
		const zend_generic_params *gp, uint32_t i)
{
	return gp->params[i].bound_kind & ZEND_GENERIC_VARIANCE_MASK;
}

/* Polarity: +1 output, -1 input, 0 invariant. */
static int zend_generics_compose_polarity(int pol, uint32_t variance)
{
	if (variance & ZEND_GENERIC_VARIANCE_OUT) return pol;
	if (variance & ZEND_GENERIC_VARIANCE_IN) return -pol;
	return 0;
}

/* Positional checks run twice: at compile time for everything the compiler
 * can decide locally (bare labels, self-references), and once more at the
 * template's first instantiation for positions inside FOREIGN generic
 * references, whose declared variance is only reliably resolvable then
 * (same moment bounds resolve). ctx->deep selects the second pass; it
 * throws instead of raising a compile error. */
typedef struct {
	const zend_class_entry *ce;
	bool deep;
	bool use_autoload;
} zend_generics_variance_ctx;

static ZEND_COLD bool zend_generics_variance_error(
		const zend_generics_variance_ctx *ctx, const zend_generic_param *param,
		int pol, const char *kind, const zend_string *member)
{
	const char *vword = (param->bound_kind & ZEND_GENERIC_VARIANCE_OUT)
		? "Covariant" : "Contravariant";
	const char *pword = (pol == 0) ? "invariant" : (pol > 0 ? "output" : "input");
	if (ctx->deep) {
		zend_throw_error(NULL,
			"%s type parameter %s of %s may not appear in an %s position (%s of %s)",
			vword, ZSTR_VAL(param->name), ZSTR_VAL(ctx->ce->name), pword,
			kind, member ? ZSTR_VAL(member) : "?");
		return false;
	}
	zend_error_noreturn(E_COMPILE_ERROR,
		"%s type parameter %s of %s may not appear in an %s position (%s of %s)",
		vword, ZSTR_VAL(param->name), ZSTR_VAL(ctx->ce->name), pword,
		kind, member ? ZSTR_VAL(member) : "?");
}

/* Does the raw slice mention any VARIANT parameter as a bare label? Used for
 * the conservative foreign-nested rule. */
static const zend_generic_param *zend_generics_slice_mentions_variant(
		const zend_class_entry *ce, const char *s, size_t len)
{
	const zend_generic_params *gp = ce->generic_params;
	const char *p = s, *end = s + len;
	while (p < end) {
		char c = *p;
		if (c == ',' || c == '<' || c == '>' || c == '.'
				|| c == '|' || c == '&' || c == '(' || c == ')') {
			p++;
			continue;
		}
		const char *label = p;
		bool qualified = false;
		while (p < end && *p != ',' && *p != '<' && *p != '>'
				&& *p != '|' && *p != '&' && *p != '(' && *p != ')') {
			if (*p == '\\') qualified = true;
			p++;
		}
		if (p < end && *p == '<') {
			continue; /* base name of a nested reference */
		}
		if (!qualified) {
			for (uint32_t i = 0; i < gp->num_params; i++) {
				if (zend_generics_param_variance(gp, i)
						&& zend_binary_strcasecmp(label, p - label,
							ZSTR_VAL(gp->params[i].name), ZSTR_LEN(gp->params[i].name)) == 0) {
					return &gp->params[i];
				}
			}
		}
	}
	return NULL;
}

static bool zend_generics_check_slice_polarity(
		const zend_generics_variance_ctx *ctx, const char *s, size_t len, int pol,
		const char *kind, const zend_string *member);

/* Compose polarity through the arguments of a generic reference whose
 * per-parameter variance is given by fgp (NULL: all slots invariant). */
static bool zend_generics_check_ref_args_polarity(
		const zend_generics_variance_ctx *ctx, const zend_generic_params *fgp,
		const char *arg, const char *end, int pol,
		const char *kind, const zend_string *member)
{
	uint32_t d = 0, idx = 0;
	const char *as = arg;
	for (const char *p = arg; p <= end; p++) {
		if (p == end || (*p == ',' && d == 0)) {
			uint32_t v = (fgp && idx < fgp->num_params)
				? zend_generics_param_variance(fgp, idx) : 0;
			if (!zend_generics_check_slice_polarity(ctx, as, p - as,
					zend_generics_compose_polarity(pol, v), kind, member)) {
				return false;
			}
			idx++;
			as = p + 1;
		} else if (*p == '<' || *p == '(') {
			d++;
		} else if (*p == '>' || *p == ')') {
			if (d) d--;
		}
	}
	return true;
}

/* One member of a slice: a bare label, or a nested reference base<args>. */
static bool zend_generics_check_member_polarity(
		const zend_generics_variance_ctx *ctx, const char *s, size_t len, int pol,
		const char *kind, const zend_string *member)
{
	const zend_class_entry *ce = ctx->ce;
	/* strip one level of parens ("(a&b)") */
	while (len >= 2 && s[0] == '(' && s[len - 1] == ')') {
		s++;
		len -= 2;
	}
	const char *lt = NULL;
	uint32_t depth = 0;
	for (const char *p = s; p < s + len; p++) {
		if (*p == '<') { if (depth == 0) { lt = p; break; } }
	}
	(void) depth;
	if (lt) {
		const char *arg = lt + 1;
		const char *end = s + len - 1; /* before closing '>' */
		if (zend_binary_strcasecmp(s, lt - s, ZSTR_VAL(ce->name), ZSTR_LEN(ce->name)) == 0) {
			/* self-reference: compose polarity through own variance */
			return zend_generics_check_ref_args_polarity(ctx, ce->generic_params,
				arg, end, pol, kind, member);
		}
		/* Foreign reference: compose through the foreign template's declared
		 * variance. Resolvable only in the deep (first-instantiation) pass;
		 * the compile-time pass defers, and slices without variant mentions
		 * need no verification at all. */
		if (!ctx->deep) {
			return true;
		}
		if (!zend_generics_slice_mentions_variant(ce, arg, end - arg)) {
			return true;
		}
		zend_string *base = zend_string_init(s, lt - s, 0);
		zend_class_entry *fce = zend_lookup_class_ex(base, NULL,
			ctx->use_autoload ? 0 : ZEND_FETCH_CLASS_NO_AUTOLOAD);
		if (!fce) {
			zend_throw_error(NULL,
				"Cannot verify variance of %s: class %s (in %s of %s) was not found",
				ZSTR_VAL(ce->name), ZSTR_VAL(base), kind,
				member ? ZSTR_VAL(member) : "?");
			zend_string_release(base);
			return false;
		}
		zend_string_release(base);
		const zend_generic_params *fgp =
			(fce->ce_flags2 & ZEND_ACC2_GENERIC_TEMPLATE) ? fce->generic_params : NULL;
		return zend_generics_check_ref_args_polarity(ctx, fgp, arg, end, pol,
			kind, member);
	}
	/* bare label */
	const zend_generic_params *gp = ce->generic_params;
	if (memchr(s, '\\', len)) {
		return true; /* qualified: never a parameter */
	}
	for (uint32_t i = 0; i < gp->num_params; i++) {
		uint32_t v = zend_generics_param_variance(gp, i);
		if (v && zend_binary_strcasecmp(s, len,
				ZSTR_VAL(gp->params[i].name), ZSTR_LEN(gp->params[i].name)) == 0) {
			if ((pol > 0 && !(v & ZEND_GENERIC_VARIANCE_OUT))
					|| (pol < 0 && !(v & ZEND_GENERIC_VARIANCE_IN))
					|| pol == 0) {
				return zend_generics_variance_error(ctx, &gp->params[i], pol,
					kind, member);
			}
			return true;
		}
	}
	return true;
}

/* Split a slice on top-level '|' / '&' (DNF members share the position's
 * polarity) and check each member. */
static bool zend_generics_check_slice_polarity(
		const zend_generics_variance_ctx *ctx, const char *s, size_t len, int pol,
		const char *kind, const zend_string *member)
{
	/* skip a "..." spread prefix (packs are never variant) */
	if (len >= 3 && s[0] == '.' && s[1] == '.' && s[2] == '.') {
		s += 3;
		len -= 3;
	}
	uint32_t d = 0;
	const char *ms = s;
	for (const char *p = s; p <= s + len; p++) {
		if (p == s + len || ((*p == '|' || *p == '&') && d == 0)) {
			if (p > ms
					&& !zend_generics_check_member_polarity(ctx, ms, p - ms, pol, kind, member)) {
				return false;
			}
			ms = p + 1;
		} else if (*p == '<' || *p == '(') {
			d++;
		} else if (*p == '>' || *p == ')') {
			if (d) d--;
		}
	}
	return true;
}

static bool zend_generics_check_type_polarity(
		const zend_generics_variance_ctx *ctx, zend_type type, int pol,
		const char *kind, const zend_string *member)
{
	if (ZEND_TYPE_HAS_LIST(type)) {
		const zend_type *lt;
		ZEND_TYPE_LIST_FOREACH(ZEND_TYPE_LIST(type), lt) {
			if (!zend_generics_check_type_polarity(ctx, *lt, pol, kind, member)) {
				return false;
			}
		} ZEND_TYPE_LIST_FOREACH_END();
		return true;
	}
	if (ZEND_TYPE_HAS_NAME(type)) {
		zend_string *name = ZEND_TYPE_NAME(type);
		return zend_generics_check_slice_polarity(ctx, ZSTR_VAL(name),
			ZSTR_LEN(name), pol, kind, member);
	}
	return true;
}

static bool zend_generics_check_variance_walk(const zend_generics_variance_ctx *ctx)
{
	const zend_class_entry *ce = ctx->ce;
	const zend_generic_params *gp = ce->generic_params;
	ZEND_ASSERT(gp && (ce->ce_flags & ZEND_ACC_INTERFACE));

	zend_function *fn;
	zend_string *key;
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(&ce->function_table, key, fn) {
		if (fn->common.fn_flags & ZEND_ACC_STATIC) {
			continue; /* statics are not part of the variance contract */
		}
		if (fn->type != ZEND_USER_FUNCTION || !fn->op_array.arg_info) {
			continue;
		}
		const zend_op_array *op = &fn->op_array;
		if ((op->fn_flags & ZEND_ACC_HAS_RETURN_TYPE)
				&& !zend_generics_check_type_polarity(ctx, op->arg_info[-1].type, +1,
					"return type", op->function_name)) {
			return false;
		}
		uint32_t n = op->num_args + ((op->fn_flags & ZEND_ACC_VARIADIC) ? 1 : 0);
		for (uint32_t i = 0; i < n; i++) {
			int pol = ZEND_ARG_SEND_MODE(&op->arg_info[i]) ? 0 : -1;
			if (!zend_generics_check_type_polarity(ctx, op->arg_info[i].type, pol,
					"parameter type", op->function_name)) {
				return false;
			}
		}
	} ZEND_HASH_FOREACH_END();

	zend_class_constant *c;
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(&ce->constants_table, key, c) {
		if (ZEND_TYPE_IS_SET(c->type)
				&& !zend_generics_check_type_polarity(ctx, c->type, +1, "constant", key)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	zend_property_info *prop;
	ZEND_HASH_MAP_FOREACH_STR_KEY_PTR(&ce->properties_info, key, prop) {
		if (!ZEND_TYPE_IS_SET(prop->type)) {
			continue;
		}
		int pol = 0;
		if (prop->hooks) {
			bool has_get = prop->hooks[ZEND_PROPERTY_HOOK_GET] != NULL;
			bool has_set = prop->hooks[ZEND_PROPERTY_HOOK_SET] != NULL;
			if (has_get && !has_set) pol = +1;
			else if (has_set && !has_get) pol = -1;
		}
		if (!zend_generics_check_type_polarity(ctx, prop->type, pol, "property", key)) {
			return false;
		}
	} ZEND_HASH_FOREACH_END();

	/* Deferred inheritance references re-expose the extended template's
	 * members, so their arguments sit at output polarity composed through
	 * that template's variance. */
	for (uint32_t i = 0; i < gp->num_deferred_interfaces; i++) {
		zend_string *ref = gp->deferred_interfaces[i];
		if (!zend_generics_check_slice_polarity(ctx, ZSTR_VAL(ref), ZSTR_LEN(ref),
				+1, "extended interface", ref)) {
			return false;
		}
	}
	return true;
}

ZEND_API void zend_generics_check_variance_positions(const zend_class_entry *ce)
{
	zend_generics_variance_ctx ctx = { ce, /* deep */ false, false };
	zend_generics_check_variance_walk(&ctx);
}

/* First-instantiation pass: verifies the positions that sit inside foreign
 * generic references, now that those templates can be resolved. Passes are
 * cached per template for the request; failures throw and are not cached. */
ZEND_API bool zend_generics_check_variance_deep(
		const zend_class_entry *ce, bool use_autoload)
{
	if (!(ce->ce_flags2 & ZEND_ACC2_GENERIC_VARIANT)) {
		return true;
	}
	zend_string *key = zend_strpprintf(0, "%p:decl", (void *) ce);
	if (EG(generics_variance_cache)
			&& zend_hash_exists(EG(generics_variance_cache), key)) {
		zend_string_release(key);
		return true;
	}
	zend_generics_variance_ctx ctx = { ce, /* deep */ true, use_autoload };
	bool ok = zend_generics_check_variance_walk(&ctx);
	if (ok) {
		if (!EG(generics_variance_cache)) {
			ALLOC_HASHTABLE(EG(generics_variance_cache));
			zend_hash_init(EG(generics_variance_cache), 16, NULL, NULL, 0);
		}
		zval zv;
		ZVAL_TRUE(&zv);
		zend_hash_add(EG(generics_variance_cache), key, &zv);
	}
	zend_string_release(key);
	return ok;
}

static bool zend_generics_arg_types_identical(zend_type a, zend_type b)
{
	if (ZEND_TYPE_PURE_MASK(a) != ZEND_TYPE_PURE_MASK(b)) {
		return false;
	}
	if (ZEND_TYPE_HAS_NAME(a) && ZEND_TYPE_HAS_NAME(b)) {
		return zend_string_equals_ci(ZEND_TYPE_NAME(a), ZEND_TYPE_NAME(b));
	}
	if (ZEND_TYPE_HAS_LIST(a) && ZEND_TYPE_HAS_LIST(b)) {
		if (ZEND_TYPE_IS_INTERSECTION(a) != ZEND_TYPE_IS_INTERSECTION(b)
				|| ZEND_TYPE_LIST(a)->num_types != ZEND_TYPE_LIST(b)->num_types) {
			return false;
		}
		for (uint32_t i = 0; i < ZEND_TYPE_LIST(a)->num_types; i++) {
			if (!zend_generics_arg_types_identical(
					ZEND_TYPE_LIST(a)->types[i], ZEND_TYPE_LIST(b)->types[i])) {
				return false;
			}
		}
		return true;
	}
	return !ZEND_TYPE_HAS_NAME(a) && !ZEND_TYPE_HAS_LIST(a)
		&& !ZEND_TYPE_HAS_NAME(b) && !ZEND_TYPE_HAS_LIST(b);
}

static zend_always_inline bool zend_generics_quiet_subtype(zend_type a, zend_type b)
{
	return zend_generics_arg_satisfies_bound_type(a, b,
		ZEND_FETCH_CLASS_NO_AUTOLOAD | ZEND_FETCH_CLASS_SILENT,
		NULL, NULL, /* quiet */ true) == 1;
}

ZEND_API bool zend_generics_variant_implements(
		const zend_class_entry *instance_ce, const zend_class_entry *iface_ce)
{
	if (!(iface_ce->ce_flags2 & ZEND_ACC2_GENERIC_INSTANCE)) {
		return false;
	}
	const zend_generic_binding *want = iface_ce->generic_binding;
	const zend_class_entry *tmpl = want->template_ce;
	if (!(tmpl->ce_flags2 & ZEND_ACC2_GENERIC_VARIANT)) {
		return false;
	}

	zend_string *key = zend_strpprintf(0, "%p:%p",
		(void *) instance_ce, (void *) iface_ce);
	if (EG(generics_variance_cache)) {
		zval *zv = zend_hash_find(EG(generics_variance_cache), key);
		if (zv) {
			bool r = Z_TYPE_P(zv) == IS_TRUE;
			zend_string_release(key);
			return r;
		}
	}

	bool result = false;
	const zend_generic_params *gp = tmpl->generic_params;
	uint32_t num_candidates = instance_ce->num_interfaces + 1;
	for (uint32_t c = 0; c < num_candidates && !result; c++) {
		const zend_class_entry *cand = (c == 0)
			? instance_ce : instance_ce->interfaces[c - 1];
		if (!(cand->ce_flags2 & ZEND_ACC2_GENERIC_INSTANCE)
				|| cand->generic_binding->template_ce != tmpl) {
			continue;
		}
		bool ok = true;
		for (uint32_t i = 0; ok && i < gp->num_params; i++) {
			uint32_t v = zend_generics_param_variance(gp, i);
			zend_type a = cand->generic_binding->args[i];
			zend_type b = want->args[i];
			if (v & ZEND_GENERIC_VARIANCE_OUT) {
				ok = zend_generics_quiet_subtype(a, b);
			} else if (v & ZEND_GENERIC_VARIANCE_IN) {
				ok = zend_generics_quiet_subtype(b, a);
			} else {
				ok = zend_generics_arg_types_identical(a, b);
			}
		}
		result = ok;
	}

	if (!EG(generics_variance_cache)) {
		ALLOC_HASHTABLE(EG(generics_variance_cache));
		zend_hash_init(EG(generics_variance_cache), 16, NULL, NULL, 0);
	}
	zval zv;
	ZVAL_BOOL(&zv, result);
	zend_hash_add(EG(generics_variance_cache), key, &zv);
	zend_string_release(key);
	return result;
}

ZEND_API zend_string *zend_generics_resolve_type_symbol(const char *sym, size_t sym_len)
{
	const zend_class_entry *scope = zend_get_executed_scope();

	if (!scope || !scope->generic_binding) {
		zend_throw_error(NULL,
			"Cannot resolve a symbolic generic type reference when no generic binding is in scope");
		return NULL;
	}
	zend_string *tmp = zend_string_init(sym, sym_len, 0);
	zend_string *sub = zend_generics_substitute_deferred_ref(
		tmp, scope->generic_binding->template_ce, scope->generic_binding);
	zend_string_release(tmp);
	return sub;
}
