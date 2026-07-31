/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Monomorphized class generics: runtime stamping of instantiations     |
   | from generic templates (prototype).                                  |
   +----------------------------------------------------------------------+
*/

#ifndef ZEND_GENERICS_H
#define ZEND_GENERICS_H

#include "zend.h"

BEGIN_EXTERN_C()

/* Stamp (or return the already-stamped) instantiation for a mangled generic
 * class name such as "App\Vec<Foo,int>". `name` is the display-cased name,
 * `lc_name` its lowercased class-table key (guaranteed to contain '<').
 * Returns NULL without an exception when the template or a required class
 * simply does not exist; throws Error for structural problems (malformed
 * name, arity mismatch, bound violations, non-generic base). */
ZEND_API zend_class_entry *zend_generics_stamp_instantiation(
		zend_string *name, zend_string *lc_name, bool use_autoload);

/* Preload: stamp every generic instantiation reachable from preloaded code
 * (transitive closure), so the stamped classes persist into SHM. */
ZEND_API void zend_generics_preload_stamp_all(void);

/* Closure creation: substitute the stamped scope's type arguments into the
 * closure's own signature copy (no-op unless the scope carries a binding and
 * the signature mentions a type parameter). */
ZEND_API void zend_generics_substitute_closure_signature(
		zend_op_array *op_array, const zend_class_entry *scope);

/* Maps a template type-parameter index (as carried by
 * ZEND_FETCH_CLASS_TYPE_PARAM opcodes) to the argument index in the scope's
 * binding. Identity without a pack; with one, post-pack params shift by the
 * instantiation's pack size. `scope_ce` must carry a generic_binding. */
/* Does a composite (mangled) name mention any of gp's parameters as a bare
 * argument? ("C<T>" yes; "C<Foo>" no; spread prefixes accepted.) */
ZEND_API bool zend_generics_name_mentions_params(
		const zend_string *name, const zend_generic_params *gp);

/* Resolve a compiler-emitted symbolic generic class reference against the
 * executing scope's binding. Owned string, or NULL with an exception. */
ZEND_API zend_string *zend_generics_resolve_type_symbol(const char *sym, size_t sym_len);

ZEND_API uint32_t zend_generics_binding_arg_index(
		const zend_class_entry *scope_ce, uint32_t param_idx);

/* Release the class-name references a binding argument holds, including
 * names inside composite (DNF) type lists; list buffers are arena-owned. */
ZEND_API void zend_generics_arg_release_names(zend_type arg);

/* Compile-time positional check for a variant interface template ('in'/'out'
 * parameters): output positions only for 'out', input only for 'in';
 * self-references compose polarity, foreign-nested mentions are invariant
 * positions in this version. Throws E_COMPILE_ERROR on violation. */
ZEND_API void zend_generics_check_variance_positions(const zend_class_entry *ce);
ZEND_API bool zend_generics_check_variance_deep(
		const zend_class_entry *ce, bool use_autoload);

/* Runtime variance edge: does `instance_ce` implement `iface_ce` through a
 * variant instantiation of the same interface template? Called from the
 * instanceof machinery after identity checks miss; never autoloads, never
 * throws; results are cached per request. */
ZEND_API bool zend_generics_variant_implements(
		const zend_class_entry *instance_ce, const zend_class_entry *iface_ce);

/* Set by the tracing JIT (opcache): called for every stamped method clone so
 * the JIT can attach a per-clone trace extension (own counters, own type
 * sources, own compiled-trace slots) instead of the template's, which the
 * clone otherwise inherits through the header memcpy. NULL when no JIT, or
 * for JIT modes that exclude generic bodies. */
ZEND_API extern void (*zend_generics_jit_clone_hook)(zend_op_array *op_array);

END_EXTERN_C()

#endif /* ZEND_GENERICS_H */
