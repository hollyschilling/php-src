/* Prototype: Surfaces (RFC draft) — named, class-scoped roles that remove
 * members from the default (public) view.
 *
 * Metadata model: a class's own surface declarations live in
 * ce->surface_decls (name -> bound interface name or null); its members'
 * surface assignments live in ce->surface_members ("m:"/"p:"/"c:" + name ->
 * packed array of surface-name strings). Surface names are unique per
 * hierarchy (no redeclaration), so names alone identify a surface; there is
 * no link-time index renumbering and lookups walk the parent chain.
 *
 * Grants (`use C with surface[...]`) compile into op_array-resident tables
 * (op_array->surface_grants, "lcclass:Surface" strings) following the same
 * copy-on-write/persistence discipline as extension imports. Enforcement is
 * at runtime in the member-resolution slow paths, keyed on the calling
 * op_array's grant table and the receiver's runtime class. (The RFC text
 * describes a static-type compile-time check; an engine that compiles files
 * independently can only approximate that at runtime — see the RFC notes.)
 */
#ifndef ZEND_SURFACES_H
#define ZEND_SURFACES_H

#include "zend.h"

BEGIN_EXTERN_C()

/* Find the class in ce's ancestry (including ce itself) that declares the
 * given surface name; NULL if the name is not a surface of the hierarchy.
 * Requires resolved parent pointers (linked class). */
ZEND_API zend_class_entry *zend_surfaces_find_owner(
	const zend_class_entry *ce, const zend_string *surface_name);

/* Fetch the surface-name set (zval of IS_ARRAY of strings) recorded for a
 * member declared by `owner`, or NULL if the member is on no surface.
 * kind: 'm' (method, lowercased name), 'p' (property), 'c' (class const). */
ZEND_API zval *zend_surfaces_member_set(
	const zend_class_entry *owner, char kind, const zend_string *member_name);
ZEND_API zval *zend_surfaces_member_set_str(
	const zend_class_entry *owner, char kind, const char *member_name, size_t len);

/* True iff the method implements a method of an interface bound to one of
 * its class's surfaces — such methods have a public "interface face" and are
 * exempt from the runtime member gate (the conversion gate is deferred). */
ZEND_API bool zend_surfaces_method_has_interface_face(const zend_function *fbc);

/* The access predicate: true iff the current scope holds one of the
 * member's surfaces — by being part of the declaring hierarchy (surfaces
 * sit above protected in the lattice), or via a `use ... with surface[...]`
 * grant in the nearest user op_array that covers receiver_ce. */
ZEND_API bool zend_surfaces_access_allowed(
	const zend_class_entry *owner, const zend_class_entry *receiver_ce,
	const zval *surface_set);

/* Link-time validation: surface redeclaration across the hierarchy, member
 * surface-name resolution and ownership, override superset rule, and scoped
 * conformance of surface-bound interfaces. Errors out on violation. Must run
 * after the class is fully linked (parent and interfaces resolved). */
void zend_surfaces_link_class(zend_class_entry *ce);

END_EXTERN_C()

#endif
