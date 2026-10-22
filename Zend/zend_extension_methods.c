#include "zend.h"
#include "zend_API.h"
#include "zend_compile.h"
#include "zend_execute.h"
#include "zend_globals.h"
#include "zend_extension_methods.h"

/* The registry lives in executor globals (one per thread under ZTS; one per
 * request either way). ZEND_BIND_EXTENSION re-registers on each request,
 * exactly as ZEND_DECLARE_FUNCTION re-declares. Function pointers are
 * borrowed -- they live in request class tables or in opcache shared memory
 * -- so teardown frees only the tables, never the entries; entry structs
 * and their name refs are request-lifetime allocations.
 *
 * Extensions declared in PRELOADED files never register under this model
 * (their top level does not re-execute per request) and consequently miss
 * cleanly at call sites; rebuilding registry entries from the persisted
 * image is a documented open issue (see the RFC). */

typedef struct _zend_extension_method_entry {
	zend_function *fn;
	zend_string *ext_name_lc; /* addref'd; NULL for anonymous extensions */
} zend_extension_method_entry;

static void ext_method_entry_dtor(zval *zv)
{
	zend_extension_method_entry *entry = Z_PTR_P(zv);
	if (entry->ext_name_lc) {
		zend_string_release(entry->ext_name_lc);
	}
	efree(entry);
}

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

/* The op_array whose code issued the current method call: nearest user frame. */
static const zend_op_array *ext_calling_op_array(void)
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

static bool ext_imports_contain(const HashTable *imports, zend_string *ext_name_lc)
{
	/* Import sets are consecutive [lc name, original-case name] pairs
	 * (zend_extension_imports_add); gating compares the lc slots. */
	uint32_t n = zend_hash_num_elements((HashTable *) imports);
	for (uint32_t i = 0; i < n; i += 2) {
		zval *entry = zend_hash_index_find((HashTable *) imports, i);
		if (entry && zend_string_equals(Z_STR_P(entry), ext_name_lc)) {
			return true;
		}
	}
	return false;
}

/* An imported extension that never loaded may be autoloadable: named
 * extensions are class-table symbols (ZEND_BIND_EXTENSION), so the ordinary
 * class autoloader can map their names to declaring files. Ask it for every
 * imported name with no class-table entry, at most once per name per request
 * — imported-but-unloadable is legal (like an unused `use` import), so the
 * negative cache is the only thing bounding re-attempts. Returns true if at
 * least one attempt ran, i.e. retrying the lookup could now succeed. Stops
 * early if an autoloader throws; the caller must propagate. */
static bool ext_autoload_missing_imports(const HashTable *imports)
{
	bool attempted = false;
	uint32_t n = zend_hash_num_elements((HashTable *) imports);

	if (!zend_autoload) {
		/* No autoloader registered: do not consume the once-per-request
		 * attempt — one registered later (e.g. a bootstrap that runs after
		 * this call site) must still be consulted. */
		return false;
	}

	for (uint32_t i = 0; i + 1 < n; i += 2) {
		zend_string *lc = Z_STR_P(zend_hash_index_find((HashTable *) imports, i));
		zend_string *orig = Z_STR_P(zend_hash_index_find((HashTable *) imports, i + 1));

		if (zend_hash_exists(EG(class_table), lc)) {
			continue; /* loaded already (as an extension or a plain class) */
		}
		if (!EG(extension_autoload_attempted)) {
			ALLOC_HASHTABLE(EG(extension_autoload_attempted));
			zend_hash_init(EG(extension_autoload_attempted), 8, NULL, NULL, 0);
		}
		if (zend_hash_add_empty_element(EG(extension_autoload_attempted), lc) == NULL) {
			continue; /* negative-cached: already attempted this request */
		}
		attempted = true;
		zend_lookup_class_ex(orig, lc, 0); /* runs the autoloader */
		if (UNEXPECTED(EG(exception))) {
			break;
		}
	}
	return attempted;
}

ZEND_API void zend_extension_methods_register(
	zend_string *target_lc, zend_class_entry *ext_ce, zend_string *ext_name_lc)
{
	HashTable *registry = zend_extension_methods_registry();
	HashTable *methods = zend_hash_find_ptr(registry, target_lc);
	zend_string *name;
	zval *zv;

	if (!methods) {
		ALLOC_HASHTABLE(methods);
		zend_hash_init(methods, 8, NULL, ext_method_entry_dtor, 0);
		zend_hash_add_ptr(registry, target_lc, methods);
	}

	ZEND_HASH_MAP_FOREACH_STR_KEY_VAL(&ext_ce->function_table, name, zv) {
		zend_function *fn = Z_PTR_P(zv);
		zend_extension_method_entry *entry = emalloc(sizeof(*entry));

		entry->fn = fn;
		entry->ext_name_lc = ext_name_lc ? zend_string_copy(ext_name_lc) : NULL;

		/* Real methods must always win; conflicts between extensions: first
		 * wins per (target, method) -- a later registration can never
		 * displace a winner, which keeps call-site cached resolutions for
		 * THIS target valid for the whole request. (A later registration on
		 * a MORE DERIVED target can still change what an uncached ancestry
		 * walk would pick; sites that already resolved keep their answer --
		 * see the RFC's caching note.)
		 * TODO(land): E_WARNING or fatal on duplicate registration.
		 * NOTE: fn may live in opcache SHM and must not be written to here. */
		if (!zend_hash_add_ptr(methods, name, entry)) {
			if (entry->ext_name_lc) {
				zend_string_release(entry->ext_name_lc);
			}
			efree(entry);
		}
	} ZEND_HASH_FOREACH_END();
}

static zend_function *ext_lookup_visible(
	const HashTable *methods, zend_string *lc_method_name,
	const zend_op_array **calling, bool *calling_known)
{
	const zend_extension_method_entry *entry = zend_hash_find_ptr(methods, lc_method_name);

	if (!entry) {
		return NULL;
	}
	if (!entry->ext_name_lc) {
		return entry->fn; /* anonymous: globally visible */
	}
	if (!*calling_known) {
		*calling = ext_calling_op_array();
		*calling_known = true;
	}
	/* Named: visible only where the calling code's op_array imported it
	 * (`use extension`, or the declaring position itself). The import set
	 * is compiled into the op_array and persisted with it, so visibility
	 * is correct under opcache, file_cache, and preloading. */
	if (*calling && (*calling)->extension_imports
	 && ext_imports_contain((*calling)->extension_imports, entry->ext_name_lc)) {
		return entry->fn;
	}
	return NULL; /* not imported here: keep walking */
}

/* Scalar receivers dispatch by value type. Lane keys share the registry
 * with class targets; no collision is possible because these names are
 * reserved and can never name a class. Named scalar extensions are
 * lexically gated exactly like class-targeted ones. */
/* Miss handler shared by the object and scalar lookups: hand every
 * imported-but-unloaded extension name of the calling op_array to the class
 * autoloader. Returns true if the caller should retry its lookup. */
static bool ext_miss_autoload(const zend_op_array **calling, bool *calling_known)
{
	if (!*calling_known) {
		*calling = ext_calling_op_array();
		*calling_known = true;
	}
	return *calling && (*calling)->extension_imports
		&& ext_autoload_missing_imports((*calling)->extension_imports)
		&& EXPECTED(!EG(exception));
}

static zend_function *ext_scalar_lookup(const zval *receiver, zend_string *lc_method_name,
	const zend_op_array **calling, bool *calling_known)
{
	const char *lane;
	size_t lane_len;
	HashTable *registry = EG(extension_method_registry);
	HashTable *methods;

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
	return ext_lookup_visible(methods, lc_method_name, calling, calling_known);
}

ZEND_API zend_function *zend_extension_methods_get_scalar(const zval *receiver, zend_string *method_name, zend_string *lc_method_name)
{
	zend_function *fn;
	const zend_op_array *calling = NULL;
	bool calling_known = false;
	zend_string *lc = lc_method_name;
	bool lc_owned = false;

	if (!lc) {
		lc = zend_string_tolower(method_name);
		lc_owned = true;
	}

	fn = ext_scalar_lookup(receiver, lc, &calling, &calling_known);
	if (!fn && ext_miss_autoload(&calling, &calling_known)) {
		fn = ext_scalar_lookup(receiver, lc, &calling, &calling_known);
	}

	if (lc_owned) {
		zend_string_release(lc);
	}
	return fn;
}

static zend_function *ext_methods_lookup(const zend_class_entry *ce, zend_string *lc_method_name,
	const zend_op_array **calling, bool *calling_known)
{
	HashTable *registry = EG(extension_method_registry);

	if (!registry || zend_hash_num_elements(registry) == 0) {
		return NULL;
	}
	/* Most-derived match first: walk the inheritance chain, then interfaces. */
	for (const zend_class_entry *c = ce; c; c = c->parent) {
		HashTable *methods = zend_hash_find_ptr_lc(registry, c->name);
		if (methods) {
			zend_function *fn = ext_lookup_visible(methods, lc_method_name, calling, calling_known);
			if (fn) {
				return fn;
			}
		}
	}
	for (uint32_t i = 0; i < ce->num_interfaces; i++) {
		HashTable *methods = zend_hash_find_ptr_lc(registry, ce->interfaces[i]->name);
		if (methods) {
			zend_function *fn = ext_lookup_visible(methods, lc_method_name, calling, calling_known);
			if (fn) {
				return fn;
			}
		}
	}
	return NULL;
}

ZEND_API zend_function *zend_extension_methods_get(const zend_class_entry *ce, zend_string *lc_method_name)
{
	const zend_op_array *calling = NULL;
	bool calling_known = false;
	zend_function *fn;

	fn = ext_methods_lookup(ce, lc_method_name, &calling, &calling_known);
	if (!fn && ext_miss_autoload(&calling, &calling_known)) {
		fn = ext_methods_lookup(ce, lc_method_name, &calling, &calling_known);
	}
	return fn;
}
