<?php
/**
 * opcache.preload file for the collections workload.
 *
 * Preloading is the one configuration in which instantiations are stamped once,
 * at server start, and persisted into opcache SHM — every other configuration
 * re-stamps them from the per-request compiler arena on every request. The
 * signature below is what makes the closed world visible to the preload
 * stamper: it walks type positions looking for names containing '<', so
 * mentioning each instantiation once in a parameter type is enough to have it
 * stamped and persisted.
 *
 * Running the collections workload with and without this file is the harness's
 * preload axis: the difference is what preloading buys per request.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib_generic.php';

function bench_preload_closed_world(
    Vec<int> $vec,
    SortedVec<int> $sorted,
    Collection<int> $collection,
): void {
}
