<?php
/**
 * Turn the raw measurements collected by run.sh into RESULTS.md + results.json.
 * Not meant to be run directly — run.sh passes everything in through the
 * environment.
 */
declare(strict_types=1);

$irData     = getenv('IR_DATA');
$growthData = getenv('GROWTH_DATA');
$out        = getenv('OUT');

/** @var array<string, array<string, array<string, int>>> $ir workload => build => config => Ir */
$ir = [];
foreach (file($irData, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$workload, $build, $config, $value] = explode('|', $line);
    $ir[$workload][$build][$config] = (int) $value;
}

/** @var array<string, array<int, array>> $growth mode => stamp count => row */
$growth = [];
foreach (file($growthData, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$mode, $count, $value, $json] = explode('|', $line, 4);
    $growth[$mode][(int) $count] = ['ir' => (int) $value] + (array) json_decode($json, true);
}

$configs = explode(' ', trim((string) getenv('CONFIGS')));

$configLabels = [
    'off'           => 'opcache off',
    'jitoff-cold'   => 'JIT off, cold',
    'jitoff-warm'   => 'JIT off, warm',
    'tracing-cold'  => 'tracing JIT, cold',
    'tracing-warm'  => 'tracing JIT, warm',
    'function-warm' => 'function JIT, warm',
];

/** Percentage change of $value against $reference, or null if either is missing. */
$pct = static function (?int $value, ?int $reference): ?float {
    if ($value === null || $reference === null || $reference === 0) {
        return null;
    }
    return round(100 * ($value - $reference) / $reference, 2);
};

$fmtPct = static fn (?float $p): string => $p === null ? '—' : sprintf('%+.2f%%', $p);
$fmtInt = static fn (?int $n): string => $n === null ? '—' : number_format($n);

$get = static fn (string $workload, string $build, string $config): ?int
    => $ir[$workload][$build][$config] ?? null;

$row = static fn (array $cells): string => '| ' . implode(' | ', $cells) . " |\n";

// ---------------------------------------------------------------------------

$md = "# Generics benchmark results\n\n";

$md .= sprintf(
    "Branch `%s` against its merge base with master, both built"
    . " `--disable-debug --enable-opcache`.\n"
    . "Metric is Callgrind instruction count (Ir) of a single request, measured through\n"
    . "`php-cgi -T`: **cold** is one request including compilation, **warm** is one request\n"
    . "in steady state after %d warmup requests have filled opcache and the JIT.\n"
    . "Generated %s.\n\n",
    getenv('GENERICS_BRANCH'),
    (int) getenv('WARMUP'),
    date('Y-m-d'),
);

// --- 1. tax ----------------------------------------------------------------

$md .= "## 1. What the branch costs code that uses no generics\n\n";
$md .= "Change in Ir from the merge-base build to the generics build, running identical\n"
     . "generics-free code. `Zend/bench.php` is php-src's own benchmark; php-parser parses\n"
     . "its own source tree; the other two are the collection pipelines below written\n"
     . "without generics.\n\n";

$taxWorkloads = [
    'canary'            => 'Zend/bench.php',
    'phpparser'         => 'php-parser',
    'collections_plain' => 'collections',
    'doctrine_plain'    => 'doctrine/collections',
];

$md .= $row(array_merge(['config'], array_values($taxWorkloads)));
$md .= $row(array_merge([':---'], array_fill(0, count($taxWorkloads), '---:')));
foreach ($configs as $config) {
    $cells = [$configLabels[$config] ?? $config];
    foreach (array_keys($taxWorkloads) as $workload) {
        $cells[] = $fmtPct($pct($get($workload, 'generics', $config), $get($workload, 'baseline', $config)));
    }
    $md .= $row($cells);
}

// --- 2. cost of using generics ---------------------------------------------

$md .= "\n## 2. What using generics costs\n\n";
$md .= "**vs plain** compares the generic version of a workload against the hand-written\n"
     . "non-generic version of the same workload on the same build — the marginal cost of\n"
     . "the generics. **vs base PHP** compares it against the non-generic version on the\n"
     . "merge-base build — the total an application pays, tax included.\n\n";

$costWorkloads = [
    'collections' => ['plain' => 'collections_plain', 'generic' => 'collections_generic'],
    'doctrine'    => ['plain' => 'doctrine_plain',    'generic' => 'doctrine_generic'],
];

$md .= $row(['config', 'collections<br>vs plain', 'collections<br>vs base PHP',
             'doctrine<br>vs plain', 'doctrine<br>vs base PHP']);
$md .= $row([':---', '---:', '---:', '---:', '---:']);
foreach ($configs as $config) {
    $cells = [$configLabels[$config] ?? $config];
    foreach ($costWorkloads as $pair) {
        $generic  = $get($pair['generic'], 'generics', $config);
        $cells[] = $fmtPct($pct($generic, $get($pair['plain'], 'generics', $config)));
        $cells[] = $fmtPct($pct($generic, $get($pair['plain'], 'baseline', $config)));
    }
    $md .= $row($cells);
}

// --- 3. preloading ---------------------------------------------------------

$md .= "\n## 3. What preloading the instantiations saves\n\n";
$md .= "An instantiation is built by cloning the template, and the result lives in the\n"
     . "per-request arena — so a warm opcache does not amortise it the way it does an\n"
     . "ordinary class, and every request rebuilds it. Preloading is the exception: it\n"
     . "stamps the closed world once, at startup, into shared memory.\n\n"
     . "Change from preloading the collections workload's instantiations; negative is\n"
     . "cheaper preloaded.\n\n";

$md .= $row(['config', 'change']);
$md .= $row([':---', '---:']);
foreach ($configs as $config) {
    if ($config === 'off') {
        continue;
    }
    $md .= $row([
        $configLabels[$config] ?? $config,
        $fmtPct($pct(
            $get('collections_preloaded', 'generics', $config),
            $get('collections_generic', 'generics', $config),
        )),
    ]);
}

$md .= "\nThat workload stamps three instantiations per request, far too few to pay for\n"
     . "preloading's own overhead. Under tracing JIT it costs more still: preloaded\n"
     . "generic families are kept interpreted on this branch, so preloading trades away\n"
     . "JIT coverage.\n\n"
     . "Stamping 200 instantiations per request instead, where the saving is the\n"
     . "dominant term:\n\n";

$runtime200 = $growth['runtime'][200] ?? null;
$preload200 = $growth['preload'][200] ?? null;
$md .= $row(['200 instantiations per request', 'change']);
$md .= $row([':---', '---:']);
$md .= $row(['instructions', $fmtPct($pct($preload200['ir'] ?? null, $runtime200['ir'] ?? null))]);
$md .= $row(['memory', $fmtPct($pct($preload200['memory'] ?? null, $runtime200['memory'] ?? null))]);

$md .= "\nPreloading is worth whatever a request would otherwise spend rebuilding\n"
     . "instantiations: nothing for a handful, most of the cost for hundreds.\n";

$md .= "\n## 4. What one more instantiation costs\n\n";
$md .= "The marginal cost of an instantiation, from the difference between stamping a\n"
     . "given number of them and stamping none — so loading the library and declaring the\n"
     . "type-argument classes cancels out. Both columns are per request: without\n"
     . "preloading, this is paid again on every one.\n\n";

$md .= $row(['instantiations', 'instructions each', 'bytes each']);
$md .= $row([':---', '---:', '---:']);
$base = $growth['runtime'][0] ?? null;
foreach ($growth['runtime'] ?? [] as $count => $data) {
    $instantiations = (int) ($data['instantiations'] ?? 0);
    if (!$count || !$base || !$instantiations) {
        continue;
    }
    $md .= $row([
        (string) $instantiations,
        $fmtInt((int) round(($data['ir'] - $base['ir']) / $instantiations)),
        $fmtInt((int) round(($data['memory'] - $base['memory']) / $instantiations)),
    ]);
}

// The per-cell instruction counts behind these tables are in results.json.

file_put_contents("$out/RESULTS.md", $md);
file_put_contents("$out/results.json", json_encode([
    'generated'          => date('c'),
    'generics_ref'       => getenv('GENERICS_REF'),
    'baseline_ref'       => getenv('BASELINE_REF'),
    'metric'             => 'callgrind_ir',
    'warmup_requests'    => (int) getenv('WARMUP'),
    'bench_iters'        => (int) getenv('BENCH_ITERS'),
    'bench_files'        => (int) getenv('BENCH_FILES'),
    'instruction_counts' => $ir,
    'instantiation_growth' => $growth,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
