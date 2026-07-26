/* Prototype: Swift-style extension methods registry (RFC draft).
 * Registry maps lc(target class name) -> HashTable of lc(method) -> entry.
 * Lives in EG(extension_method_registry): per request, per thread under ZTS.
 * Named extensions (extension Name on Target) are lexically gated: their
 * methods resolve only from op_arrays whose file imported them via
 * `use extension` (the declaring position imports itself). Import sets are
 * compiled into op_arrays and persisted by opcache, so gating is correct
 * under SHM caching, file_cache, and preloading. Anonymous extensions are
 * globally visible.
 */
#ifndef ZEND_EXTENSION_METHODS_H
#define ZEND_EXTENSION_METHODS_H

#include "zend.h"

BEGIN_EXTERN_C()

/* Frees the per-request registry tables (entries are borrowed pointers). */
void zend_extension_methods_request_shutdown(void);

/* Called when an `extension ... { ... }` block's synthetic CE is linked.
 * ext_name_lc is NULL for anonymous blocks (globally visible); for named
 * blocks it is the lowercased fully-qualified extension name. */
ZEND_API void zend_extension_methods_register(
	zend_string *target_lc, zend_class_entry *ext_ce, zend_string *ext_name_lc);

/* Fallback lookup: walks ce and its ancestry/interfaces for a registered
 * method visible from the calling frame's op_array. */
ZEND_API zend_function *zend_extension_methods_get(const zend_class_entry *ce, zend_string *lc_method_name);

/* Fallback lookup for non-object receivers (string/int/float/bool/array),
 * consulted where a method call would otherwise raise "Call to a member
 * function on ...". lc_method_name may be NULL if only method_name is known. */
ZEND_API zend_function *zend_extension_methods_get_scalar(const zval *receiver, zend_string *method_name, zend_string *lc_method_name);

END_EXTERN_C()

#endif
