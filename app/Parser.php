<?php

namespace App;

use RuntimeException;
use Throwable;

final class Parser
{
    private const int EXPECTED_PATH_COUNT = 268;
    private const int PATH_OFFSET = 19;
    private const int URL_PREFIX_LENGTH = 25;
    private const int TIMESTAMP_LENGTH = 25;
    private const int LINE_SUFFIX_LENGTH = self::TIMESTAMP_LENGTH + 2;
    private const int DATE_START_YEAR = 2021;
    private const int DATE_END_YEAR = 2026;
    private const int DEFAULT_WORKERS = 8;
    private const int MULTI_PROCESS_THRESHOLD_BYTES = 134_217_728;
    private const int READ_CHUNK_BYTES = 262_144;
    private const int COUNTER_BYTES = 2;
    private const array MONTH_OFFSETS = [0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    private const array LEAP_MONTH_OFFSETS = [0, 0, 31, 60, 91, 121, 152, 182, 213, 244, 274, 305, 335];

    public static function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        [$dateStrings, $yearOffsets] = self::buildCalendar();
        $workerCount = self::resolveWorkerCount($inputPath);

        if ($workerCount > 1) {
            [$paths, $counts] = self::aggregateMultiProcess($inputPath, $yearOffsets, count($dateStrings), $workerCount);
        } else {
            [$paths, $counts] = self::aggregateSingleProcess($inputPath, $yearOffsets);
        }

        self::writeJson($outputPath, $paths, $counts, $dateStrings);
    }

    private static function resolveWorkerCount(string $inputPath): int
    {
        $configured = getenv('TEMPEST_PARSER_WORKERS');

        if ($configured !== false && $configured !== '') {
            return max(1, (int) $configured);
        }

        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            return 1;
        }

        $fileSize = filesize($inputPath);

        if ($fileSize === false || $fileSize < self::MULTI_PROCESS_THRESHOLD_BYTES) {
            return 1;
        }

        return self::DEFAULT_WORKERS;
    }

    /**
     * @return array{0: list<string>, 1: array<int, array<int, int>>}
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

    /**
     * @return array{0: list<string>, 1: array<int, array<int, int>>}
     */
    private static function aggregateMultiProcess(string $inputPath, array $yearOffsets, int $dateCount, int $workerCount): array
    {
        [$paths, $slugIndexes] = self::discoverPaths($inputPath);
        $boundaries = self::calculateChunkBoundaries($inputPath, $workerCount);
        $actualWorkers = count($boundaries) - 1;

        if ($actualWorkers <= 1) {
            return self::aggregateSingleProcess($inputPath, $yearOffsets);
        }

        $sockets = [];
        $pids = [];

        for ($workerIndex = 0; $workerIndex < $actualWorkers; $workerIndex++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

            if ($pair === false) {
                throw new RuntimeException('Unable to create worker socket pair');
            }

            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Unable to fork worker process');
            }

            if ($pid === 0) {
                fclose($pair[0]);
                self::runWorker(
                    $inputPath,
                    $boundaries[$workerIndex],
                    $boundaries[$workerIndex + 1],
                    $slugIndexes,
                    $yearOffsets,
                    $dateCount,
                    $pair[1],
                );
            }

            fclose($pair[1]);
            $sockets[$pid] = $pair[0];
            $pids[] = $pid;
        }

        $counts = array_fill(0, count($paths), []);

        foreach ($sockets as $pid => $socket) {
            $buffer = self::readSocket($socket);
            fclose($socket);
            self::mergeWorkerBuffer($counts, $buffer, $dateCount);
            unset($sockets[$pid]);
        }

        foreach ($pids as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);

            if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                throw new RuntimeException("Worker {$pid} exited abnormally");
            }
        }

        return [$paths, $counts];
    }

    /**
     * @return array{0: list<string>, 1: array<string, int>}
     */
    private static function discoverPaths(string $inputPath): array
    {
        $handle = fopen($inputPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open input file: {$inputPath}");
        }

        stream_set_read_buffer($handle, 0);

        $paths = [];
        $slugIndexes = [];

        while (($line = fgets($handle)) !== false) {
            $comma = strpos($line, ',');

            if ($comma === false) {
                continue;
            }

            $slug = substr($line, self::URL_PREFIX_LENGTH, $comma - self::URL_PREFIX_LENGTH);

            if (isset($slugIndexes[$slug])) {
                continue;
            }

            $slugIndexes[$slug] = count($paths);
            $paths[] = '/blog/' . $slug;

            if (count($paths) >= self::EXPECTED_PATH_COUNT) {
                break;
            }
        }

        fclose($handle);

        return [$paths, $slugIndexes];
    }

    /**
     * @return list<int>
     */
    private static function calculateChunkBoundaries(string $inputPath, int $workerCount): array
    {
        $handle = fopen($inputPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open input file: {$inputPath}");
        }

        stream_set_read_buffer($handle, 0);

        $fileSize = filesize($inputPath);

        if ($fileSize === false) {
            fclose($handle);
            throw new RuntimeException("Unable to determine file size: {$inputPath}");
        }

        $boundaries = [0];
        $step = (int) floor($fileSize / $workerCount);

        for ($workerIndex = 1; $workerIndex < $workerCount; $workerIndex++) {
            $offset = $step * $workerIndex;

            if ($offset <= 0 || $offset >= $fileSize) {
                continue;
            }

            fseek($handle, $offset);
            fgets($handle);

            $boundary = ftell($handle);

            if ($boundary === false || $boundary >= $fileSize) {
                continue;
            }

            if ($boundary > $boundaries[array_key_last($boundaries)]) {
                $boundaries[] = $boundary;
            }
        }

        $boundaries[] = $fileSize;
        fclose($handle);

        return $boundaries;
    }

    /**
     * @param array<string, int> $slugIndexes
     */
    private static function runWorker(
        string $inputPath,
        int $start,
        int $end,
        array $slugIndexes,
        array $yearOffsets,
        int $dateCount,
        mixed $socket,
    ): never {
        try {
            $buffer = self::parseChunk($inputPath, $start, $end, $slugIndexes, $yearOffsets, $dateCount);
            self::writeSocket($socket, $buffer);
            fclose($socket);
            exit(0);
        } catch (Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage() . PHP_EOL);

            if (is_resource($socket)) {
                fclose($socket);
            }

            exit(1);
        }
    }

    /**
     * @param array<string, int> $slugIndexes
     */
    private static function parseChunk(
        string $inputPath,
        int $start,
        int $end,
        array $slugIndexes,
        array $yearOffsets,
        int $dateCount,
    ): string {
        $handle = fopen($inputPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open input file: {$inputPath}");
        }

        stream_set_read_buffer($handle, 0);
        fseek($handle, $start);

        $buffer = str_repeat("\0", count($slugIndexes) * $dateCount * self::COUNTER_BYTES);
        $remaining = $end - $start;
        $carry = '';
        $nextBytes = self::byteLookup();

        while ($remaining > 0) {
            $chunk = fread($handle, min(self::READ_CHUNK_BYTES, $remaining));

            if ($chunk === false) {
                fclose($handle);
                throw new RuntimeException('Unable to read chunk data');
            }

            if ($chunk === '') {
                break;
            }

            $remaining -= strlen($chunk);
            $data = $carry . $chunk;

            if ($remaining > 0) {
                $lastNewline = strrpos($data, "\n");

                if ($lastNewline === false) {
                    $carry = $data;
                    continue;
                }

                $carry = substr($data, $lastNewline + 1);
                $data = substr($data, 0, $lastNewline + 1);
            } else {
                $carry = '';
            }

            self::consumeBuffer($data, $buffer, $slugIndexes, $yearOffsets, $dateCount, $nextBytes);
        }

        if ($carry !== '') {
            self::consumeBuffer($carry, $buffer, $slugIndexes, $yearOffsets, $dateCount, $nextBytes);
        }

        fclose($handle);

        return $buffer;
    }

    /**
     * @param array<string, int> $slugIndexes
     * @param list<string> $nextBytes
     */
    private static function consumeBuffer(
        string $data,
        string &$countsBuffer,
        array $slugIndexes,
        array $yearOffsets,
        int $dateCount,
        array $nextBytes,
    ): void {
        $lineStart = 0;
        $dataLength = strlen($data);

        while ($lineStart < $dataLength) {
            $comma = strpos($data, ',', $lineStart + self::URL_PREFIX_LENGTH);

            if ($comma === false) {
                break;
            }

            $slug = substr($data, $lineStart + self::URL_PREFIX_LENGTH, $comma - $lineStart - self::URL_PREFIX_LENGTH);
            $pathIndex = $slugIndexes[$slug] ?? null;

            if ($pathIndex === null) {
                throw new RuntimeException("Encountered undiscovered slug: {$slug}");
            }

            $dateIndex = self::dateIndexFromLine($data, $comma, $yearOffsets);
            $counterOffset = (($pathIndex * $dateCount) + $dateIndex) * self::COUNTER_BYTES;
            $lowByte = ord($countsBuffer[$counterOffset]);

            if ($lowByte !== 255) {
                $countsBuffer[$counterOffset] = $nextBytes[$lowByte];
            } else {
                $countsBuffer[$counterOffset] = "\0";
                $highOffset = $counterOffset + 1;
                $highByte = ord($countsBuffer[$highOffset]);

                if ($highByte === 255) {
                    throw new RuntimeException('Counter overflowed 16-bit storage');
                }

                $countsBuffer[$highOffset] = $nextBytes[$highByte];
            }

            $lineStart = isset($data[$comma + self::LINE_SUFFIX_LENGTH - 1]) && $data[$comma + self::LINE_SUFFIX_LENGTH - 1] === "\n"
                ? $comma + self::LINE_SUFFIX_LENGTH
                : $dataLength;
        }
    }

    /**
     * @param array<int, array<int, int>> $counts
     */
    private static function mergeWorkerBuffer(array &$counts, string $buffer, int $dateCount): void
    {
        $byteOffset = 0;

        foreach ($counts as $pathIndex => &$pathCounts) {
            for ($dateIndex = 0; $dateIndex < $dateCount; $dateIndex++, $byteOffset += self::COUNTER_BYTES) {
                $value = ord($buffer[$byteOffset]) | (ord($buffer[$byteOffset + 1]) << 8);

                if ($value === 0) {
                    continue;
                }

                if (isset($pathCounts[$dateIndex])) {
                    $pathCounts[$dateIndex] += $value;
                } else {
                    $pathCounts[$dateIndex] = $value;
                }
            }
        }

        unset($pathCounts);
    }

    /**
     * @return list<string>
     */
    private static function byteLookup(): array
    {
        static $lookup = null;

        if ($lookup !== null) {
            return $lookup;
        }

        $lookup = [];

        for ($byte = 0; $byte < 256; $byte++) {
            $lookup[$byte] = chr(($byte + 1) & 255);
        }

        return $lookup;
    }

    private static function writeSocket(mixed $socket, string $buffer): void
    {
        $written = 0;
        $length = strlen($buffer);

        while ($written < $length) {
            $chunk = fwrite($socket, substr($buffer, $written, 65_536));

            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Unable to write worker buffer to socket');
            }

            $written += $chunk;
        }
    }

    private static function readSocket(mixed $socket): string
    {
        $buffer = '';

        while (! feof($socket)) {
            $chunk = fread($socket, 65_536);

            if ($chunk === false) {
                throw new RuntimeException('Unable to read worker buffer from socket');
            }

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        return $buffer;
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
        $handle = fopen($outputPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open output file: {$outputPath}");
        }

        stream_set_write_buffer($handle, 1_048_576);

        fwrite($handle, '{');

        $firstPath = true;

        foreach ($paths as $pathIndex => $path) {
            if ($counts[$pathIndex] === []) {
                continue;
            }

            ksort($counts[$pathIndex]);

            fwrite($handle, $firstPath ? "\n" : ",\n");
            fwrite($handle, '    ' . self::encodePath($path) . ': {');

            $firstDate = true;

            foreach ($counts[$pathIndex] as $dateIndex => $count) {
                fwrite($handle, $firstDate ? "\n" : ",\n");
                fwrite($handle, '        "' . $dateStrings[$dateIndex] . '": ' . $count);
                $firstDate = false;
            }

            fwrite($handle, "\n    }");
            $firstPath = false;
        }

        fwrite($handle, $firstPath ? '}' : "\n}");
        fclose($handle);
    }

    private static function encodePath(string $path): string
    {
        static $encoded = [];

        return $encoded[$path] ??= '"' . str_replace('/', '\\/', $path) . '"';
    }
}