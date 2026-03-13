---
name: challenge-spec
description: Reference for the 100-million-row PHP challenge rules, input/output formats, validation, and constraints. Use when implementing or reviewing Parser.php, validating output, or understanding challenge requirements.
---

# 100-Million-Row Challenge Specification

## Task

Implement `Parser::parse(string $inputPath, string $outputPath)` in `app/Parser.php`.
Read CSV, aggregate page visits by URL path and date, write sorted JSON.

## Input Format

CSV, no header. Each line: `<full_url>,<ISO-8601-timestamp>\n`

```
https://stitcher.io/blog/php-enums,2024-01-24T01:16:58+00:00
```

- 254 unique URLs, all `https://stitcher.io/blog/<slug>`
- Timestamps: ISO 8601 with `+00:00` timezone
- 100M rows for real benchmark (~7-8GB file)
- Comma separates URL from timestamp (URL never contains commas)

### URL Structure

All URLs follow: `https://stitcher.io/blog/<slug>`
- Fixed prefix: `https://stitcher.io` (20 chars)
- Path always starts with `/blog/`
- Extract path by stripping first 20 characters

### Timestamp Structure

Format: `YYYY-MM-DDThh:mm:ss+00:00`
- Always 25 characters
- Date portion: first 10 characters of timestamp
- All timestamps are UTC (+00:00)

## Output Format

JSON with `JSON_PRETTY_PRINT`. Keys: URL paths. Values: objects of `"YYYY-MM-DD": count`.
Dates sorted ascending per path. Paths NOT required to be sorted.

```json
{
    "\/blog\/php-enums": {
        "2024-01-24": 1
    }
}
```

Slashes in paths are escaped as `\/` (PHP's default `json_encode` behavior).

## Constraints

- **PHP only** (PHP 8.5+), no FFI, no JIT
- No internet access, no external tools
- Must not modify input data
- Must work within project directory
- Server: Mac Mini M1, 12GB RAM
- Extensions available: pcntl, shmop, sockets, posix, sysvmsg, sysvsem, sysvshm, igbinary, plus standard extensions

## Commands

```bash
php tempest data:generate              # 1M rows (default)
php tempest data:generate 100_000_000  # 100M rows
php tempest data:parse                 # Run parser
php tempest data:validate              # Validate against test data
```

## Validation

`data:validate` runs parser on `data/test-data.csv` (1000 rows) and compares byte-for-byte against `data/test-data-expected.json`.

## Leaderboard Context

Top times on Mac Mini M1 (100M rows):
- ~2.0s (multi-thread leader)
- ~2.2s (second place)
- Key techniques likely involve: pcntl_fork parallelism, low-level string ops, buffered I/O, pre-computed lookup tables
