# Tempest 100M Row Challenge benchmark tracking

This directory stores local benchmark evidence and iteration notes for the parser implementation work on the Ubuntu VM.

## Baseline status

- **Timestamp:** 2026-03-13T11:44:02Z
- **Commit:** `9113298c1f2771a317d0117f733561c23af70148`
- **State before implementation:** non-functional

### Commands run before any code changes

1. `php tempest data:validate`
2. `php tempest data:parse --input-path=/workspace/data/test-data.csv --output-path=/workspace/data/test-data-actual.json`

### Result

Both commands failed because `app/Parser.php` still throws `Exception('TODO')`.

This baseline is intentionally recorded as the starting point requested in the PRD. Meaningful performance comparisons begin with the first correct implementation that passes validation.

## Iteration 1 — correctness-first single-core parser

- **Status:** validation passing
- **Implementation notes:**
  - replaced the TODO parser with a working static parser entrypoint
  - added a `data:parse` fast path in `tempest`
  - aligned `DataParseCommand` with the same static parser entrypoint
  - preserved first-seen path ordering
  - used dense date indexing for ascending date output

### Validation

- `php tempest data:validate` → **PASS**

### 1M development dataset benchmark

- command: `php tempest data:parse --input-path=/workspace/data/data.csv --output-path=/workspace/data/data.json`
- runs:
  - 0.527736s
  - 0.539025s
  - 0.540818s
- **median:** `0.539025s`
- **average:** `0.535860s`

### Next target

Introduce the multi-process parsing path, benchmark single-core vs multi-core, and then iterate on merge/output performance.
