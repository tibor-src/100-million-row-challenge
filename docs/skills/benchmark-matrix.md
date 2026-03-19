# Benchmark Matrix SOP

## Canonical datasets

1. `data/test-data.csv` (validation only)
2. `data/data.csv` (primary 100M benchmark)
3. additional generated datasets (seed-tagged) run sequentially if storage is constrained

## Required modes per retained experiment

1. **Single-core**

```bash
TEMPEST_PARSER_WORKERS=1 php tempest data:parse --input-path=... --output-path=...
```

2. **Multi-core (default host-aware)**

```bash
php tempest data:parse --input-path=... --output-path=...
```

3. **Multi-core tuned (if used)**

```bash
TEMPEST_PARSER_WORKERS=8 TEMPEST_PARSER_CHUNK_TARGET_BYTES=... TEMPEST_PARSER_CHUNK_BYTES=... php tempest data:parse --input-path=... --output-path=...
```

## Comparator checkpoint

Run at regular intervals:

- local branch
- PR #3
- PR #203
- PR #266

Always use the same input file and report wall time in the same way.

## Reporting format

For each mode/config:

- runs list
- median
- average
- whether result is a retained default, optional tuned mode, or rejected experiment
