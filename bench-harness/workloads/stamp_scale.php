<?php
/**
 * Instantiation-growth probe: what does one more stamped instantiation cost?
 *
 * "Monomorphisation makes memory grow without bound" is the standard objection
 * to reified generics, and it is only answerable with a per-instantiation
 * number. This workload stamps STAMP_COUNT distinct instantiations of the same
 * template and reports the resulting memory.
 *
 * Measure it at two counts (0 and N) and take the delta: everything else in the
 * run — loading the library, declaring the N type-argument classes — is present
 * in both, so it cancels, and (mem(N) - mem(0)) / N is the marginal cost of an
 * instantiation. The same subtraction applied to the Callgrind number gives the
 * instructions one stamping costs.
 *
 * Env: STAMP_COUNT (default 100), the number of instantiations to stamp.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib_generic.php';
// The type-argument classes are loaded unconditionally, so their cost is
// identical at every STAMP_COUNT and drops out of the subtraction. They live in
// a file rather than an eval() because the preload cell needs to preload them.
// typeargs.php and preload_scale.php are both written by generate.php; the
// class-name prefix below has to match the one it uses, or the preload cell
// silently stamps a second, disjoint set of instantiations.
require_once __DIR__ . '/typeargs.php';

const TYPE_ARG_PREFIX = 'Targ';

// Note: `?:` would swallow a deliberate STAMP_COUNT=0, which is the baseline
// half of the subtraction this workload exists for.
$count = getenv('STAMP_COUNT') !== false ? (int) getenv('STAMP_COUNT') : 100;

// Stamp by dynamic class name: a class-table miss on a name containing '<'
// routes into the stamper, which is the same path `new Vec<int>()` takes.
$stamped = [];
for ($i = 0; $i < $count; $i++) {
    $name      = 'Vec<' . TYPE_ARG_PREFIX . "$i>";
    $stamped[] = new $name([]);
}

// Instantiations are ordinary entries in the class table, so they can be
// counted from userland without any engine instrumentation. Enumerating them
// this way does NOT stamp anything. Interfaces live in a separate list, and a
// generic class implementing a generic interface stamps both.
$instantiations = 0;
foreach ([...get_declared_classes(), ...get_declared_interfaces()] as $class) {
    if (str_contains($class, '<')) {
        $instantiations++;
    }
}

echo json_encode([
    'stamp_count'    => $count,
    'instantiations' => $instantiations,
    // emalloc'd bytes: the stamper allocates the class entry, the binding and
    // every cloned method from the per-request compiler arena, which is
    // emalloc-backed, so this tracks stamping directly. memory_get_usage(true)
    // is reported too but only moves in 2 MB chunks.
    'memory'         => memory_get_usage(),
    'memory_peak'    => memory_get_peak_usage(),
    'memory_real'    => memory_get_usage(true),
]), "\n";
