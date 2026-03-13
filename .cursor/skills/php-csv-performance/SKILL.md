---
name: php-csv-performance
description: PHP performance techniques for fast CSV parsing, string manipulation, I/O, memory management, and multi-process parallelism. Use when optimizing Parser.php, researching PHP performance approaches, or profiling bottlenecks in the 100M row challenge.
---

# PHP CSV Performance Techniques

## Optimization Layers

Approach improvements in this order. Each layer compounds on the previous.

### Layer 1: I/O Strategy

**Reading:**
- `fread()` with large buffers (1-4MB) beats `fgets()` line-by-line
- `file_get_contents()` + manual splitting for smaller files
- `stream_set_read_buffer()` to tune OS-level buffering
- Read raw bytes, find newlines manually with `strpos()`/`strrpos()`

**Writing:**
- Build output string in memory, single `file_put_contents()`
- Or buffer with `fwrite()` + `stream_set_write_buffer()`
- Pre-allocate string buffers where possible

### Layer 2: String Parsing

**URL path extraction:**
- All URLs share prefix `https://stitcher.io` (20 chars)
- `substr($line, 20, $commaPos - 20)` extracts path directly
- Avoid `parse_url()` -- too slow at scale
- Avoid `explode()` on the full line when `strpos()` + `substr()` suffice

**Date extraction:**
- Timestamp starts after the comma: position `$commaPos + 1`
- Date is first 10 chars of timestamp: `substr($timestamp, 0, 10)`
- Combine: `substr($line, $commaPos + 1, 10)` to get date directly

**Combined single-pass:**
```php
$commaPos = strrpos($line, ',');
$path = substr($line, 20, $commaPos - 20);
$date = substr($line, $commaPos + 1, 10);
```

### Layer 3: Data Structures

**Aggregation array:**
```php
$data[$path][$date] = ($data[$path][$date] ?? 0) + 1;
```

- PHP arrays are hash tables -- O(1) lookup
- 254 paths x ~1826 possible dates = ~463K max entries (fits in memory)
- Consider using `isset()` checks to avoid null coalescing overhead

**Pre-allocated path map:**
- Build a map of full URL prefix -> path before parsing
- Or use the fixed 20-char offset since all URLs share the same domain

### Layer 4: Multi-Process Parallelism

**pcntl_fork approach:**
- Split input file into N chunks (by byte offset, not line count)
- Find nearest newline boundary for each chunk
- Fork N child processes, each processes its chunk
- Merge results in parent

**Chunk boundary finding:**
```php
$fileSize = filesize($inputPath);
$chunkSize = (int)ceil($fileSize / $numWorkers);
// Seek to chunk boundary, scan forward to next newline
```

**Result passing (options):**
1. **Temp files:** Each child writes partial results to temp file, parent merges
2. **Shared memory (shmop):** Direct memory sharing, complex serialization
3. **Sockets/pipes:** Stream results back to parent via socket pairs
4. **SysV shared memory:** `shm_attach()` / `shm_put_var()`

**Worker count:** CPU cores on M1 = 8 (4 performance + 4 efficiency). Test 4-8 workers.

### Layer 5: Output Generation

**Sorting:**
- `ksort()` each path's date array (dates sort lexicographically as strings)
- Only ~254 paths, each with limited dates -- sorting is cheap

**JSON encoding:**
- `json_encode($data, JSON_PRETTY_PRINT)` is the standard approach
- Manual JSON building can be faster but risky for correctness
- If manual: escape `/` as `\/` in paths, format numbers without quotes

### Layer 6: Advanced Techniques

**Avoiding function call overhead:**
- Inline operations where possible
- Use `match` or pre-computed lookup tables
- Minimize per-line function calls

**Memory-mapped I/O concept:**
- PHP lacks true mmap, but large `fread()` blocks approximate it
- Read 2-4MB blocks, process in-place

**Binary search for newlines:**
- When splitting file into chunks, use `fseek()` + `fread()` small buffer to find newline boundaries

## Profiling

```bash
time php tempest data:parse                    # Wall clock
php -d memory_limit=2G tempest data:parse      # Test memory limits
```

For detailed profiling, add `microtime(true)` markers around phases:
1. File reading
2. Line parsing
3. Aggregation
4. Sorting
5. JSON encoding
6. File writing

## Additional References

- For challenge rules and I/O format, see the [challenge-spec skill](../challenge-spec/SKILL.md)
- For tracking solution iterations, see the [solution-journal skill](../solution-journal/SKILL.md)
