# Iteration 4 — assumption audit and robustness checks

- **Safety commit:** `2e8dc397355f49bfca8b61f9e5577d9aa149d826`
- **Latest audit commit:** `fd855c2368a759201afc5d7b97672e6d64b02391`
- **Status:** PASS

## Audit goal

Review the parser for assumptions that are useful for challenge performance but are not guaranteed by the original high-level requirement, then test the parser on fresh and adversarial inputs.

## Fast-path assumptions identified

The optimized path still assumes:

1. fixed challenge URL prefix
2. fixed timestamp shape
3. fast-path year window `2021..2026`
4. current challenge-style slug discovery behavior

Those assumptions are acceptable for performance on the known challenge shape, but they are not all guaranteed by the original requirement text.

## Change made

Added a **generic correctness fallback**:

- the parser still tries the optimized fast path first
- if it sees unsupported data for the fast path, it falls back to a generic parser/writer that only depends on the actual CSV + JSON contract

This keeps challenge performance work intact while removing correctness dependence on the hardcoded fast-path year range.

## New-data tests

### 1) Custom adversarial dataset

A custom CSV was created with:
- 300 unique slugs not coming from `Visit::all()`
- years `2018` and `2031`
- forced `TEMPEST_PARSER_WORKERS=8`

Command:

```bash
TEMPEST_PARSER_WORKERS=8 php tempest data:parse --input-path=/tmp/parser-edge.csv --output-path=/tmp/parser-edge.json
```

Result:

- parser output matched a separate reference implementation byte-for-byte

### 2) Fresh unseeded generated dataset

Command:

```bash
php tempest data:generate 200000 /tmp/parser-random.csv 0 --force
php tempest data:parse --input-path=/tmp/parser-random.csv --output-path=/tmp/parser-random.json
```

Result:

- parser output matched a separate reference implementation byte-for-byte

## Discovery-threshold experiment

Because the new fallback added a small safety cost, discovery-idle thresholds were tested:

| Idle bytes | Result |
|------------|--------|
| 524288 | 1.787849s median |
| 1048576 | 1.770884s median |

### Outcome

The original **1MB** threshold remained the best of the tested options and was retained.

## Current default result

Command:

```bash
php tempest data:parse --input-path=/workspace/data/data.csv --output-path=/workspace/data/data.json
```

Result:

- **10M auto spot-check:** `1.782011s`

## Why the retained approaches stay

- **Fast path + fallback**: best balance between challenge speed and broader correctness
- **Sodium merge**: still the strongest measured aggregation win
- **Manual JSON writer**: still avoids end-of-run structure rebuild overhead
- **No extra unrolling**: tested, but no measurable win

## What I tried and could not improve further

Within the PHP-only constraints and local measurements, I did not find a new retained technique that beat the current settings after:

- merge-strategy experiments
- chunk-size experiments
- unroll experiments
- worker-count spot checks
- discovery-threshold experiments
- correctness-safety fallback work

Any next step that might beat the current result likely needs a deeper redesign rather than another small micro-optimization.
