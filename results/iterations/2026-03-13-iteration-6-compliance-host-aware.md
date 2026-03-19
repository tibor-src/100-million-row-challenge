# Iteration 6 — compliance-first execution and host-aware defaults

- **Compliance commit:** `3c3584b44dd20f18f4f0b5b04d55d48c308499c2`
- **Host-aware commit:** `36fc2a6c571a9520da67984fd5f7228c1558efb3`
- **Status:** PASS

## Goals in this iteration

1. remove `data:parse` bootstrap bypass for stricter compliance
2. move from fixed machine assumptions to host-aware default strategy
3. continue full-size (`100_000_000`) benchmarking and compare against top references

## Compliance change

Removed the direct parse bypass from `tempest`. Parsing now runs through the standard command path.

## Host-aware strategy implemented

Defaults now adapt by host/input:

- worker count derived from detected CPU count and file-size threshold
- chunk-target sizing derived from file size and worker count
- read chunk-size tiering based on file size
- fast-path trust mode defaults only on large files (and is still env-overridable)

## Full-size benchmark evidence

### Default (host-aware) 100M runs

| Run | Time |
|-----|------|
| 1 | 3.174s |
| 2 | 3.195s |
| 3 | 3.226s |

- **Median:** `3.195s`

### Explicit tuned config under compliance path

Config:

```bash
TEMPEST_PARSER_MERGE=sodium
TEMPEST_PARSER_WORKERS=8
TEMPEST_PARSER_CHUNK_TARGET_BYTES=33554432
TEMPEST_PARSER_CHUNK_BYTES=163840
```

- **Median:** `3.174s` (3 runs)

### Profiled default run (100M)

```bash
TEMPEST_PARSER_PROFILE=1 php tempest data:parse ...
```

- wall time observed: `3.547s`
- dominant cost remains worker parsing / aggregation phase

## Comparison context versus top references

Earlier local medians from top references on same 100M file:

- PR #203: ~`2.865s`
- PR #3: ~`3.291s`

Current compliance-path medians are still behind PR #203, and around PR #3 range depending on run variance.

## Additional gains attempted

After implementing host-aware defaults, further merge/worker/chunk parameter sweeps were repeated. No additional retained change in this pass gave a strong, repeatable structural improvement.

## Conclusion

This iteration prioritized compliance interpretation and adaptability across host types. It improved default robustness and configurability, but absolute peak performance remains constrained by parser hot-loop cost in the worker phase.
