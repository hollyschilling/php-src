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
ZEND_API uint32_t zend_generics_binding_arg_index(
		const zend_class_entry *scope_ce, uint32_t param_idx);

/* Generic METHODS (spike): stamp (or fetch the cached) instantiation of a
 * generic method for a mangled call name such as "map<App\Price>". Returns
 * NULL with an exception set on any structural failure. */
ZEND_API zend_function *zend_generics_get_method_instantiation(
		zend_class_entry *ce, zend_string *method_name, zend_string *lc_name);

/* Resolve a compiler-emitted method-symbol class reference ("U",
 * "Sequence<U>") against the executing method instantiation's binding (and,
 * secondarily, the scope's class binding). Owned string or NULL + throw. */
ZEND_API zend_string *zend_generics_resolve_method_symbol(const char *sym, size_t sym_len);

END_EXTERN_C()

#endif /* ZEND_GENERICS_H */
