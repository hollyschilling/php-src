<?php
/**
 * doctrine/collections pipeline, unmodified library, ordinary call sites.
 *
 * Runs on both builds against the unconverted library, which makes it the
 * real-library "tax" probe; the converted counterpart is
 * doctrine_pipeline_generic.php. The two files are identical except for the
 * library they autoload and the type arguments at the construction site.
 *
 * Env: COLLECTIONS_SRC (library src dir), BENCH_ITERS.
 */
declare(strict_types=1);

$src = getenv('COLLECTIONS_SRC')
    ?: (sys_get_temp_dir() . '/generics-bench-realworld/collections/src');

spl_autoload_register(static function (string $class) use ($src): void {
    $prefix = 'Doctrine\\Common\\Collections\\';
    if (str_starts_with($class, $prefix)) {
        $path = $src . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

use Doctrine\Common\Collections\ArrayCollection;

$iterations = (int) (getenv('BENCH_ITERS') ?: 2000);
$data       = range(1, 100);
$double     = static fn (int $n): int => $n * 2;
$isEven     = static fn (int $n): bool => $n % 4 === 0;
$isBig      = static fn (int $k, int $n): bool => $n > 100;

$acc = 0;
for ($i = 0; $i < $iterations; $i++) {
    $collection      = new ArrayCollection($data);
    $doubled         = $collection->map($double);
    $evens           = $doubled->filter($isEven);
    [$big, $small]   = $evens->partition($isBig);
    $head            = $evens->slice(0, 5);

    $acc += $evens->count();
    $acc += $big->count();
    $acc += count($head);
    $acc += $collection->contains(50) ? 1 : 0;
}

echo "acc=$acc\n";
