<?php
// Fresh-process benchmark; configure OPcache/JIT through -d options.
declare(strict_types=1);
class SteadyGeneric<T> {
    public int $total = 0;
    public function keep(T $value, int $iterations): T {
        for ($i = 0; $i < $iterations; $i++) { $this->total += $i; }
        return $value;
    }
}
class SteadyPlain {
    public int $total = 0;
    public function keep(int $value, int $iterations): int {
        for ($i = 0; $i < $iterations; $i++) { $this->total += $i; }
        return $value;
    }
}
function steadyCalls(object $box, int $count): int {
    $sum = 0;
    for ($i = 0; $i < $count; $i++) { $sum += $box->keep($i, 0); }
    return $sum;
}
$kind = $argv[1] ?? 'generic';
$workload = $argv[2] ?? 'loop';
$box = $kind === 'generic' ? new SteadyGeneric<int>() : new SteadyPlain();
$box->keep(42, 10000);
steadyCalls($box, 100);
$box->total = 0;
$before = opcache_get_status(false)['jit']['buffer_free'];
$start = hrtime(true);
if ($workload === 'loop') {
    $result = $box->keep(42, 3000000);
    if ($result !== 42 || $box->total !== 4499998500000) { throw new Exception('Wrong loop result'); }
} else {
    $result = steadyCalls($box, 200000);
    if ($result !== 19999900000) { throw new Exception('Wrong call result'); }
}
$elapsed = (hrtime(true) - $start) / 1e6;
echo json_encode([
    'kind' => $kind, 'workload' => $workload, 'milliseconds' => $elapsed,
    'jit_bytes_used_after_warmup' => $before - opcache_get_status(false)['jit']['buffer_free'],
    'result' => $result,
]), "\n";
