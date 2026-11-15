<?php
// Run with opcache.enable_cli=1, file_update_protection=0, jit_buffer_size=16M,
// and jit=function or jit=disable. Compare fresh processes with the same build.
declare(strict_types=1);

class JitClosureChurn<T> {
    public function make(int $offset): Closure {
        return function (T $value) use ($offset): T { return $value + $offset; };
    }
}

$iterations = (int) ($argv[1] ?? 10000);
$factory = new JitClosureChurn<int>();
$warmup = $factory->make(0);
if ($warmup(42) !== 42) { throw new Exception('Wrong warmup result'); }
unset($warmup);
$before = opcache_get_status(false)['jit']['buffer_free'];
$memory = memory_get_usage();
$start = hrtime(true);
$sum = 0;
for ($i = 0; $i < $iterations; $i++) {
    $closure = $factory->make($i);
    $sum += $closure($i);
}
$elapsed = (hrtime(true) - $start) / 1e6;
if ($sum !== $iterations * ($iterations - 1)) { throw new Exception('Wrong sum'); }
echo json_encode([
    'iterations' => $iterations,
    'milliseconds' => $elapsed,
    'jit_bytes_used_after_warmup' => $before - opcache_get_status(false)['jit']['buffer_free'],
    'request_memory_growth_bytes' => memory_get_usage() - $memory,
    'sum' => $sum,
]), "\n";
