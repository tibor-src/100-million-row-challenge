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

## Iteration 2 — adaptive multi-process parser

- **Commit:** `3c3c24c28cfaf1ff6c7db4b69f2c7d559732ac16`
- **Status:** validation passing
- **Implementation notes:**
  - added chunked multi-process parsing with `pcntl_fork()`
  - used UNIX socket pairs for worker → parent buffer transfer
  - added dense 16-bit counter buffers in worker processes
  - switched final output generation to a manual pretty JSON writer
  - kept an adaptive threshold so small inputs stay single-process while large inputs switch to multi-process automatically

### Validation and parity

- `php tempest data:validate` → **PASS** in `0.005252123s`
- forced `TEMPEST_PARSER_WORKERS=8` on `data/test-data.csv` matched the expected JSON exactly
- 1M dataset: single-worker and multi-worker outputs matched byte-for-byte
- 10M dataset: single-worker and 4-worker outputs matched byte-for-byte

### 1M development dataset comparison

Forced worker counts:

| Workers | Median / observed time |
|---------|-------------------------|
| 1 | 1.096921s |
| 4 | 0.887613s |
| 6 | 0.937956s |
| 8 | 0.995030s |

Observation: on the smaller 1M dataset, worker coordination overhead means 4 workers beat 8 workers locally.

### 10M development dataset comparison

| Workers | Time |
|---------|------|
| 1 | 6.084797s |
| 4 | 1.938152s |
| 8 | 1.927250s |
| auto | 1.930535s |

Observation: on the 10M dataset, 8 workers narrowly beat 4 workers and delivered a **3.16x speedup** over single-worker mode.

### Adaptive decision

The parser now stays single-process for inputs below `128MB` and switches to multi-process above that threshold. This preserves low-overhead behavior on small datasets while still enabling strong multi-core scaling on larger inputs.

## Iteration 3 — Phase D tuning outcomes

- **Final tuned commit:** `f350689c0be8ccacf199b2664125c626a7a99adc`
- **Status:** validation passing

### Merge strategy comparison on 10M data

| Merge mode | Workers | Time |
|------------|---------|------|
| manual | 8 | 1.999212s |
| sodium | 8 | 1.761746s |

**Decision:** keep `sodium_add()` as the default worker-buffer merge strategy. It reduced total runtime by about **0.24s** on the 10M dataset while still matching manual-merge output byte-for-byte.

### Read chunk size comparison on 10M data with sodium merge

| Chunk bytes | Time |
|-------------|------|
| 131072 | 1.735399s (median of 3) |
| 262144 | 1.771460s (median of 3) |
| 524288 | 1.778049s |
| 1048576 | 1.805119s |

**Decision:** keep `131072` bytes as the default worker read chunk size. It was the clearest local win after sodium merge became the default.

### Unroll comparison on 10M data with sodium merge

| Unroll factor | Time |
|---------------|------|
| 1 | 1.775947s |
| 2 | 1.776658s |

**Decision:** keep unroll factor `1`. The simple duplicated two-row loop was slightly slower and did not justify replacing the default path.

### Worker count spot-check with final merge/chunk settings

| Workers | Time |
|---------|------|
| 4 | 1.778773s |
| 8 | 1.780939s |

**Decision:** local 4-worker and 8-worker results were effectively tied after tuning. The parser keeps the 8-worker default for large files because the challenge target is still an 8-core benchmark host, while `TEMPEST_PARSER_WORKERS` remains available for local overrides.

### Final current default result

- `php tempest data:parse --input-path=/workspace/data/data.csv --output-path=/workspace/data/data.json`
- **10M auto benchmark:** `1.734105s`

Compared with the earlier adaptive multi-process default (`1.930535s`), the retained Phase D tuning changes improved the 10M default path by about **0.196s**.
