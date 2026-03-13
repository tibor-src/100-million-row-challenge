# Iteration 1 — correctness-first single-core parser

- **Dataset:** `data/test-data.csv` and `data/data.csv`
- **Workers:** 1
- **Status:** PASS

## Changes

- Replaced the TODO parser with a working static parser implementation.
- Preserved output key order by keeping paths in first-seen input order.
- Added dense date indexing to keep per-path dates naturally sortable.
- Added a fast `data:parse` entrypoint in `tempest`.
- Updated the framework command to call the same static parser entrypoint.

## Validation

```bash
php tempest data:validate
```

Result: **PASS** in `0.003050089s`

## 1M-row benchmark

Command:

```bash
php tempest data:parse --input-path=/workspace/data/data.csv --output-path=/workspace/data/data.json
```

Runs:

1. `0.527736s`
2. `0.539025s`
3. `0.540818s`

- **Median:** `0.539025s`
- **Average:** `0.535860s`

## Observations

- The correctness-first implementation is already fast on the local 1M-row development dataset.
- The next step is not more single-core micro-tuning; it is adding the multi-process path and measuring whether merge overhead pays for itself on this VM.
