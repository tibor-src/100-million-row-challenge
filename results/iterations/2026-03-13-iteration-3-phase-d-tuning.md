# Iteration 3 — Phase D tuning outcomes

- **Final tuned commit:** `f350689c0be8ccacf199b2664125c626a7a99adc`
- **Status:** PASS

## Goals for this phase

- Compare merge approaches instead of assuming one.
- Compare read chunk sizes with the winning merge mode.
- Compare simple loop unrolling against the default loop.
- Confirm worker-count behavior after the retained tuning changes.

## Validation

```bash
php tempest data:validate
```

Result: **PASS** in `0.005175829s`

## Merge strategy comparison (10M dataset)

| Merge mode | Workers | Time |
|------------|---------|------|
| manual | 8 | 1.999212s |
| sodium | 8 | 1.761746s |

### Outcome

`sodium_add()` was a clear win and became the default worker-buffer merge strategy.

## Read chunk size comparison (10M dataset, sodium merge)

| Chunk bytes | Runs / time |
|-------------|-------------|
| 131072 | 1.732257s, 1.735399s, 1.819632s |
| 262144 | 1.756033s, 1.771460s, 1.782500s |
| 524288 | 1.778049s |
| 1048576 | 1.805119s |

### Outcome

`131072` bytes had the best median among the repeated runs and became the default worker read chunk size.

## Unroll comparison (10M dataset, sodium merge)

| Unroll factor | Time |
|---------------|------|
| 1 | 1.775947s |
| 2 | 1.776658s |

### Outcome

The duplicated two-row unrolled loop was slightly slower. It was kept as an opt-in tuning control for benchmarking, but the default remains `1`.

## Worker count comparison after retained tuning

| Workers | Runs |
|---------|------|
| 4 | 1.754892s, 1.778773s |
| 8 | 1.775347s, 1.780939s |

### Outcome

4 and 8 workers were effectively tied on this 4-core Ubuntu VM once sodium merge and 128KB chunking were in place. The parser keeps the 8-worker default for large files because the challenge target is still an 8-core benchmark host, while `TEMPEST_PARSER_WORKERS` remains available for local overrides.

## Final default benchmark

Command:

```bash
php tempest data:parse --input-path=/workspace/data/data.csv --output-path=/workspace/data/data.json
```

Result:

- **10M auto benchmark:** `1.734105s`

## Takeaway

The retained Phase D tuning changes were:

1. default merge strategy → `sodium`
2. default worker read chunk size → `131072`

The discarded Phase D tuning changes were:

1. manual merge
2. larger worker read chunks
3. unroll factor `2`
