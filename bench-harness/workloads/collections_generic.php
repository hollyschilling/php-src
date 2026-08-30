<?php
/**
 * The same pipeline as collections_plain.php, written with generics. Runs on
 * the generics build only.
 *
 * Because instantiations are stamped into the per-request class table (and,
 * without preloading, are NOT persisted to opcache SHM), the warm number here
 * still carries the per-request stamping cost — that is precisely the cost this
 * workload is meant to expose. `new static()` inside map()/filter()/slice()
 * propagates the instantiation, so the whole chain stays monomorphised.
 *
 * Keep this file and collections_plain.php identical except for the generics.
 */
declare(strict_types=1);

// require_once, not require: under the preload cell the library is already
// loaded by preload_generic.php.
require_once __DIR__ . '/lib_generic.php';

$iterations = (int) (getenv('BENCH_ITERS') ?: 2000);
$data       = range(1, 100);
$double     = static fn (int $n): int => $n * 2;
$isEven     = static fn (int $n): bool => $n % 4 === 0;

$acc = 0;
for ($i = 0; $i < $iterations; $i++) {
    $vec     = new Vec<int>($data);
    $doubled = $vec->map($double);
    $evens   = $doubled->filter($isEven);
    $head    = $evens->slice(0, 5);

    $acc += $evens->count();
    $acc += $head->first();
    $acc += $vec->contains(50) ? 1 : 0;

    foreach ($head as $n) {
        $acc += $n;
    }

    $sorted = new SortedVec<int>($head->toArray());
    $sorted->add($i);
    $acc += $sorted->median();
    $acc += $sorted->last();
}

echo "acc=$acc\n";
