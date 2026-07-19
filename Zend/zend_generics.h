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

END_EXTERN_C()

#endif /* ZEND_GENERICS_H */
