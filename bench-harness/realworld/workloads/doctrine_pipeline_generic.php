<?php
/**
 * doctrine/collections pipeline against the native-generics conversion of the
 * library. Generics build only.
 *
 * Identical to doctrine_pipeline.php except for the two things the conversion
 * forces: it autoloads the converted library, and the construction site spells
 * its type arguments. There is no way to avoid that second change on this
 * branch — with no type-parameter defaults, `new ArrayCollection($data)` is an
 * error once ArrayCollection takes type parameters — so the difference between
 * these two files is also a fair picture of what converting a library costs its
 * consumers. `createFrom()` inside the library uses `new static()`, so the
 * instantiation propagates through map/filter/partition/slice without any
 * further annotation.
 *
 * Env: COLLECTIONS_SRC (library src dir), BENCH_ITERS.
 */
declare(strict_types=1);

$src = getenv('COLLECTIONS_SRC')
    ?: (sys_get_temp_dir() . '/generics-bench-realworld/collections-generic/src');

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
    $collection      = new ArrayCollection<int, int>($data);
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
