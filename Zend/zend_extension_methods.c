#include "zend.h"
#include "zend_API.h"
#include "zend_globals.h"
#include "zend_extension_methods.h"

/* The registry lives in executor globals (one per thread under ZTS; one per
 * request either way). ZEND_BIND_EXTENSION re-registers on each request,
 * exactly as ZEND_DECLARE_FUNCTION re-declares. Function pointers are
 * borrowed -- they live in request class tables or in opcache shared memory
 * -- so teardown frees only the tables, never the entries.
 *
 * Extensions declared in PRELOADED files never register under this model
 * (their top level does not re-execute per request) and consequently miss
 * cleanly at call sites; rebuilding registry entries from the persisted
 * image is a documented open issue (see the RFC). */

static zend_always_inline HashTable *zend_extension_methods_registry(void)
{
	HashTable *registry = EG(extension_method_registry);
	if (!registry) {
		ALLOC_HASHTABLE(registry);
		zend_hash_init(registry, 8, NULL, NULL, 0);
		EG(extension_method_registry) = registry;
	}
	return registry;
}

void zend_extension_methods_request_shutdown(void)
{
	HashTable *registry = EG(extension_method_registry);
	if (registry) {
		HashTable *methods;
		ZEND_HASH_FOREACH_PTR(registry, methods) {
			zend_hash_destroy(methods);
			FREE_HASHTABLE(methods);
		} ZEND_HASH_FOREACH_END();
		zend_hash_destroy(registry);
		FREE_HASHTABLE(registry);
		EG(extension_method_registry) = NULL;
	}
}

ZEND_API void zend_extension_methods_register(zend_string *target_lc, zend_class_entry *ext_ce)
{
	HashTable *registry = zend_extension_methods_registry();
	HashTable *methods = zend_hash_find_ptr(registry, target_lc);
	zend_string *name;
	zval *zv;

	if (!methods) {
		ALLOC_HASHTABLE(methods);
		zend_hash_init(methods, 8, NULL, NULL, 0);
		zend_hash_add_ptr(registry, target_lc, methods);
	}

	ZEND_HASH_MAP_FOREACH_STR_KEY_VAL(&ext_ce->function_table, name, zv) {
		zend_function *fn = Z_PTR_P(zv);
		/* Real methods must always win; conflicts between extensions: first
		 * wins -- which also keeps every call site's cached resolution valid
		 * for the whole request (registration is monotonic and a later
		 * registration can never displace a winner).
		 * TODO(land): E_WARNING or fatal on duplicate registration.
		 * NOTE: fn may live in opcache SHM and must not be written to here. */
		zend_hash_add_ptr(methods, name, fn);
	} ZEND_HASH_FOREACH_END();
}

/* Scalar receivers dispatch by value type. Lane keys share the registry
 * with class targets; no collision is possible because these names are
 * reserved and can never name a class. */
ZEND_API zend_function *zend_extension_methods_get_scalar(const zval *receiver, zend_string *method_name, zend_string *lc_method_name)
{
	const char *lane;
	size_t lane_len;
	HashTable *registry = EG(extension_method_registry);
	HashTable *methods;
	zend_function *fn;

	if (!registry || zend_hash_num_elements(registry) == 0) {
		return NULL;
	}

	switch (Z_TYPE_P(receiver)) {
		case IS_STRING: lane = "string"; lane_len = sizeof("string") - 1; break;
		case IS_LONG:   lane = "int";    lane_len = sizeof("int") - 1;    break;
		case IS_DOUBLE: lane = "float";  lane_len = sizeof("float") - 1;  break;
		case IS_TRUE:
		case IS_FALSE:  lane = "bool";   lane_len = sizeof("bool") - 1;   break;
		case IS_ARRAY:  lane = "array";  lane_len = sizeof("array") - 1;  break;
		default:
			return NULL;
	}

	methods = zend_hash_str_find_ptr(registry, lane, lane_len);
	if (!methods) {
		return NULL;
	}

	if (lc_method_name) {
		fn = zend_hash_find_ptr(methods, lc_method_name);
	} else {
		zend_string *lc = zend_string_tolower(method_name);
		fn = zend_hash_find_ptr(methods, lc);
		zend_string_release(lc);
	}
	return fn;
}


ZEND_API zend_function *zend_extension_methods_get(const zend_class_entry *ce, zend_string *lc_method_name)
{
	HashTable *registry = EG(extension_method_registry);
	if (!registry || zend_hash_num_elements(registry) == 0) {
		return NULL;
	}
	/* Most-derived match first: walk the inheritance chain, then interfaces. */
	for (const zend_class_entry *c = ce; c; c = c->parent) {
		HashTable *methods = zend_hash_find_ptr_lc(registry, c->name);
		if (methods) {
			zend_function *fn = zend_hash_find_ptr(methods, lc_method_name);
			if (fn) {
				return fn;
			}
		}
	}
	for (uint32_t i = 0; i < ce->num_interfaces; i++) {
		HashTable *methods = zend_hash_find_ptr_lc(registry, ce->interfaces[i]->name);
		if (methods) {
			zend_function *fn = zend_hash_find_ptr(methods, lc_method_name);
			if (fn) {
				return fn;
			}
		}
	}
	return NULL;
}
