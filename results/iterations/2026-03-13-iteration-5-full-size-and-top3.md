# Iteration 5 — full-size profiling and top-3 upstream comparison

- **Profiling commit:** `3d286342e0017af44824f7c2a00765d2f11caeae`
- **Optimization commit:** `897fb5c46d20369a561aba7806faf8457f439da8`
- **Status:** PASS

## Scope for this iteration

Per updated request, this iteration used:

1. full challenge size (`100_000_000` rows) for optimization decisions
2. runtime tracing/perf analysis (not only output correctness)
3. upstream top-3 solution comparison from the main challenge repository
4. continued optimization attempts until no clear retained win was found

## Full-size baseline profile

Command:

```bash
TEMPEST_PARSER_PROFILE=1 php tempest data:parse --input-path=/workspace/data/data.csv --output-path=/workspace/data/data.json
```

Early profile (before packed-tail redesign):

- total parse time: **12.748s**
- dominant phase: worker parsing time within socket-read phase (~11.85s)
- write phase was secondary (~0.79s)

## Applied redesigns

1. **Packed-tail backward parse path** in workers for full-size throughput
2. **Dynamic chunk-range scheduling** (configurable target chunk size)
3. **Userspace output buffering** to reduce write-call overhead
4. **8-bit worker counters + merge-time expansion** to lower per-row increment overhead
5. **profiling hooks** (`TEMPEST_PARSER_PROFILE=1`) for phase-level timing
6. Optional **trust mode** (`TEMPEST_PARSER_TRUST_FAST_PATH=1`) for aggressive fast-path benchmarking

## Correctness checks

- `php tempest data:validate` passed after each major change.
- 100M output hash matched top-reference outputs:

```text
5c08af94000c24081c6fe425c788c1d858ebffe4aec33bd4016500d1c6ca2ac2
```

## Top-3 upstream comparison (same 100M dataset, local VM)

Branches benchmarked from local clones:

- PR #3 (`xHeaven`) commit `b62093d2950d97ad81afd03dddf12d5b392bba80`
- PR #203 (`AcidBurn86`) commit `7e052488772dd8f25cad905eb4878c2a143530ed`
- PR #266 (`random767435`) commit `789f6bedce68241260a66acd852a422ee066cce6`

Medians observed:

| Solution | Median |
|----------|--------|
| PR #203 (AcidBurn86) | **2.865s** |
| PR #3 (xHeaven) | 3.291s |
| PR #266 (random767435) | 3.398s |
| Our parser (safe default) | 3.442s |

## Additional post-compare optimization attempts

After confirming we were not yet faster than PR #203, these alternatives were tested:

1. read chunk-size sweeps
2. worker-count sweeps
3. chunk-target sweeps
4. merge-mode sweeps
5. optional trust-mode sweeps

Best optional trust-mode median:

- **2.951s** (`workers=8`, `chunk_target=32MB`, `chunk=160KB`, trust mode on)

Still slower than PR #203 median in local tests.

## What was unnecessary or non-critical at 100M scale

The profile and experiments showed:

1. output phase was no longer dominant after buffering changes
2. path/date safety checks in the hottest fast-path loops cost measurable time
3. fixed coarse chunking underutilized worker throughput compared with dynamic chunk ranges

## End-of-iteration conclusion

Within this pass, no additional PHP-only change produced a stable retained win over the current best safe configuration. The remaining performance gap to the local fastest upstream solution appears to require either:

- accepting more aggressive default assumptions, or
- another deeper parser architecture shift.
