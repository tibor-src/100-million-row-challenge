<?php

declare(strict_types=1);

/**
 * Standalone microbench harness for parser hot-path function choices.
 *
 * Usage:
 *   php results/microbench/function_call_bench.php
 */

function runCase(string $name, Closure $fn, int $repeats = 3): array
{
    $times = [];
    $peak = 0;

    for ($run = 0; $run < $repeats; $run++) {
        gc_collect_cycles();
        $start = hrtime(true);
        $fn();
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $times[] = $elapsedMs;
        $peak = max($peak, memory_get_peak_usage(true));
    }

    sort($times);

    return [
        'name' => $name,
        'runs_ms' => $times,
        'median_ms' => $times[(int) floor((count($times) - 1) / 2)],
        'avg_ms' => array_sum($times) / count($times),
        'peak_mb' => $peak / 1_048_576,
    ];
}

$results = [];

$iterations = 8_000_000;
$remainingSeed = 163_840;

$results[] = runCase('min()_vs_branch:min', static function () use ($iterations, $remainingSeed): void {
    $remaining = $remainingSeed;
    $sum = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $sum += min(147_456, $remaining);
        $remaining = ($remaining === 1) ? $remainingSeed : $remaining - 1;
    }

    if ($sum === -1) {
        echo 'never';
    }
});

$results[] = runCase('min()_vs_branch:branch', static function () use ($iterations, $remainingSeed): void {
    $remaining = $remainingSeed;
    $sum = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $sum += ($remaining > 147_456) ? 147_456 : $remaining;
        $remaining = ($remaining === 1) ? $remainingSeed : $remaining - 1;
    }

    if ($sum === -1) {
        echo 'never';
    }
});

$chunk = str_repeat('x', 180) . "https://stitcher.io/blog/some-path,2026-02-27T07:26:44+00:00\n";
$pointer = strlen($chunk) - 2;
$extractIterations = 10_000_000;

$results[] = runCase('date_extract:substr(7)', static function () use ($chunk, $pointer, $extractIterations): void {
    $hash = 0;

    for ($i = 0; $i < $extractIterations; $i++) {
        $short = substr($chunk, $pointer - 22, 7);
        $hash ^= ord($short[0]);
    }

    if ($hash === -1) {
        echo 'never';
    }
});

$results[] = runCase('date_extract:char_concat(7)', static function () use ($chunk, $pointer, $extractIterations): void {
    $hash = 0;

    for ($i = 0; $i < $extractIterations; $i++) {
        $short = $chunk[$pointer - 22]
            . $chunk[$pointer - 21]
            . $chunk[$pointer - 20]
            . $chunk[$pointer - 19]
            . $chunk[$pointer - 18]
            . $chunk[$pointer - 17]
            . $chunk[$pointer - 16];
        $hash ^= ord($short[0]);
    }

    if ($hash === -1) {
        echo 'never';
    }
});

$counterSize = 268 * 2191;
$counterBuffer = str_repeat("\0", $counterSize);
$lookup = [];

for ($byte = 0; $byte < 256; $byte++) {
    $lookup[chr($byte)] = chr(($byte + 1) & 255);
}

$idxIterations = 12_000_000;

$results[] = runCase('byte_increment:lookup_table', static function () use ($counterBuffer, $lookup, $idxIterations): void {
    $buffer = $counterBuffer;
    $mask = strlen($buffer) - 1;

    for ($i = 0; $i < $idxIterations; $i++) {
        $idx = ($i * 13) & $mask;
        $buffer[$idx] = $lookup[$buffer[$idx]];
    }

    if ($buffer === '') {
        echo 'never';
    }
});

$results[] = runCase('byte_increment:ord_chr', static function () use ($counterBuffer, $idxIterations): void {
    $buffer = $counterBuffer;
    $mask = strlen($buffer) - 1;

    for ($i = 0; $i < $idxIterations; $i++) {
        $idx = ($i * 13) & $mask;
        $buffer[$idx] = chr((ord($buffer[$idx]) + 1) & 255);
    }

    if ($buffer === '') {
        echo 'never';
    }
});

$map = [];

for ($i = 0; $i < 512; $i++) {
    $map['k' . $i] = $i;
}

$probeIterations = 10_000_000;

$results[] = runCase('map_lookup:coalesce', static function () use ($map, $probeIterations): void {
    $sum = 0;

    for ($i = 0; $i < $probeIterations; $i++) {
        $sum += $map['k' . ($i & 511)] ?? 0;
    }

    if ($sum === -1) {
        echo 'never';
    }
});

$results[] = runCase('map_lookup:isset_then_index', static function () use ($map, $probeIterations): void {
    $sum = 0;

    for ($i = 0; $i < $probeIterations; $i++) {
        $key = 'k' . ($i & 511);
        $sum += isset($map[$key]) ? $map[$key] : 0;
    }

    if ($sum === -1) {
        echo 'never';
    }
});

$lineChunk = str_repeat("https://stitcher.io/blog/some-path,2026-02-27T07:26:44+00:00\n", 2500);
$searchIterations = 200_000;

$results[] = runCase('newline_search:strrpos', static function () use ($lineChunk, $searchIterations): void {
    $sum = 0;

    for ($i = 0; $i < $searchIterations; $i++) {
        $sum += strrpos($lineChunk, "\n");
    }

    if ($sum === -1) {
        echo 'never';
    }
});

$results[] = runCase('newline_search:reverse_loop', static function () use ($lineChunk, $searchIterations): void {
    $sum = 0;
    $len = strlen($lineChunk);

    for ($i = 0; $i < $searchIterations; $i++) {
        for ($p = $len - 1; $p >= 0; $p--) {
            if ($lineChunk[$p] === "\n") {
                $sum += $p;
                break;
            }
        }
    }

    if ($sum === -1) {
        echo 'never';
    }
});

$report = [
    'php' => PHP_VERSION,
    'timestamp' => gmdate('c'),
    'results' => $results,
];

echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL;
