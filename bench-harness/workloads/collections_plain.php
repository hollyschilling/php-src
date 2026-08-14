<?php
/**
 * Non-generic twin of collections_generic.php. Runs on BOTH builds, so it is
 * both the "tax" probe (baseline vs generics build, same non-generic code) and
 * the apples-to-apples base the generic version is compared against.
 *
 * Keep this file and collections_generic.php identical except for the generics.
 */
declare(strict_types=1);

// require_once for symmetry with collections_generic.php, which needs it.
require_once __DIR__ . '/lib_plain.php';

$iterations = (int) (getenv('BENCH_ITERS') ?: 2000);
$data       = range(1, 100);
$double     = static fn (int $n): int => $n * 2;
$isEven     = static fn (int $n): bool => $n % 4 === 0;

$acc = 0;
for ($i = 0; $i < $iterations; $i++) {
    $vec     = new Vec($data);
    $doubled = $vec->map($double);
    $evens   = $doubled->filter($isEven);
    $head    = $evens->slice(0, 5);

    $acc += $evens->count();
    $acc += $head->first();
    $acc += $vec->contains(50) ? 1 : 0;

    foreach ($head as $n) {
        $acc += $n;
    }

    $sorted = new SortedVec($head->toArray());
    $sorted->add($i);
    $acc += $sorted->median();
    $acc += $sorted->last();
}

echo "acc=$acc\n";
