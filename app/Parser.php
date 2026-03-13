<?php

namespace App;

use JsonException;
use RuntimeException;

final class Parser
{
    private const int PATH_OFFSET = 19;
    private const int URL_PREFIX_LENGTH = 25;
    private const int DATE_START_YEAR = 2021;
    private const int DATE_END_YEAR = 2026;
    private const array MONTH_OFFSETS = [0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    private const array LEAP_MONTH_OFFSETS = [0, 0, 31, 60, 91, 121, 152, 182, 213, 244, 274, 305, 335];

    public static function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        [$dateStrings, $yearOffsets] = self::buildCalendar();
        [$paths, $counts] = self::aggregateSingleProcess($inputPath, $yearOffsets);

        self::writeJson($outputPath, $paths, $counts, $dateStrings);
    }

    /**
     * @return array{0: list<string>, 1: array<string, array<int, int>>}
     */
    private static function aggregateSingleProcess(string $inputPath, array $yearOffsets): array
    {
        $handle = fopen($inputPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open input file: {$inputPath}");
        }

        stream_set_read_buffer($handle, 0);

        $paths = [];
        $pathIndexes = [];
        $counts = [];

        while (($line = fgets($handle)) !== false) {
            $comma = strpos($line, ',');

            if ($comma === false) {
                continue;
            }

            $path = substr($line, self::PATH_OFFSET, $comma - self::PATH_OFFSET);

            if (! isset($pathIndexes[$path])) {
                $pathIndexes[$path] = count($paths);
                $paths[] = $path;
                $counts[] = [];
            }

            $pathIndex = $pathIndexes[$path];
            $dateIndex = self::dateIndexFromLine($line, $comma, $yearOffsets);

            if (isset($counts[$pathIndex][$dateIndex])) {
                $counts[$pathIndex][$dateIndex]++;
            } else {
                $counts[$pathIndex][$dateIndex] = 1;
            }
        }

        fclose($handle);

        return [$paths, $counts];
    }

    private static function dateIndexFromLine(string $line, int $comma, array $yearOffsets): int
    {
        $year = (ord($line[$comma + 1]) - 48) * 1000
            + (ord($line[$comma + 2]) - 48) * 100
            + (ord($line[$comma + 3]) - 48) * 10
            + (ord($line[$comma + 4]) - 48);

        $month = (ord($line[$comma + 6]) - 48) * 10
            + (ord($line[$comma + 7]) - 48);

        $day = (ord($line[$comma + 9]) - 48) * 10
            + (ord($line[$comma + 10]) - 48);

        $monthOffsets = self::isLeapYear($year)
            ? self::LEAP_MONTH_OFFSETS
            : self::MONTH_OFFSETS;

        return $yearOffsets[$year] + $monthOffsets[$month] + $day - 1;
    }

    /**
     * @return array{0: list<string>, 1: array<int, int>}
     */
    private static function buildCalendar(): array
    {
        $yearOffsets = [];
        $dateStrings = [];
        $index = 0;

        for ($year = self::DATE_START_YEAR; $year <= self::DATE_END_YEAR; $year++) {
            $yearOffsets[$year] = $index;
            $maxDay = self::isLeapYear($year) ? 366 : 365;

            for ($dayOfYear = 1; $dayOfYear <= $maxDay; $dayOfYear++) {
                $dateStrings[$index++] = date('Y-m-d', strtotime("{$year}-01-01 + " . ($dayOfYear - 1) . ' days'));
            }
        }

        return [$dateStrings, $yearOffsets];
    }

    private static function isLeapYear(int $year): bool
    {
        return $year % 400 === 0 || ($year % 4 === 0 && $year % 100 !== 0);
    }

    /**
     * @param list<string> $paths
     * @param array<int, array<int, int>> $counts
     * @param list<string> $dateStrings
     */
    private static function writeJson(string $outputPath, array $paths, array $counts, array $dateStrings): void
    {
        $output = [];

        foreach ($paths as $pathIndex => $path) {
            if ($counts[$pathIndex] === []) {
                continue;
            }

            ksort($counts[$pathIndex]);

            $dateCounts = [];

            foreach ($counts[$pathIndex] as $dateIndex => $count) {
                $dateCounts[$dateStrings[$dateIndex]] = $count;
            }

            $output[$path] = $dateCounts;
        }

        try {
            $json = json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode output JSON', previous: $exception);
        }

        if (file_put_contents($outputPath, $json) === false) {
            throw new RuntimeException("Unable to write output file: {$outputPath}");
        }
    }
}