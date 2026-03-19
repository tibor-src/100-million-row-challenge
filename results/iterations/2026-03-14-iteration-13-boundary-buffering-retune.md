# Iteration 13 — boundary buffering fix + host-default retune

## Goal

Continue where iteration 12 ended, focusing on simplification/perf opportunities that can still move the 100M median down without changing PHP/runtime constraints.

## Retained parser changes

Commit: `6947335`

1. removed unbuffered read configuration for:
   - `discoverPaths()`
   - `calculateChunkRanges()`
2. retuned default large-file heuristics:
   - chunk target:
     - `>=4GB` → `16MB`
     - `>=1GB` → `24MB`
   - read chunk bytes:
     - `>=2GB` → `196608`
     - `>=512MB` → `147456`

## Why this change was tested

Profile output in the baseline of this cycle showed an unexpectedly expensive chunk-boundary phase (`multi_boundaries_ms ~768ms`) in the tuned 100M path.

Hypothesis: unbuffered mode (`stream_set_read_buffer(..., 0)`) on discovery/boundary file handles is unnecessary for these sparse alignment reads and is creating avoidable overhead.

## Validation and correctness

- `php tempest data:validate` → PASS
- output hash parity on 100M remained unchanged (`5c08af...2ac2`)

## Performance evidence

### Profile before vs after buffering fix

- before:
  - wall: `5.188s`
  - `multi_boundaries_ms=768.022`
  - `total_parse_ms=4856.268`
- after:
  - wall: `3.125s`
  - `multi_boundaries_ms=0.628`
  - `total_parse_ms=2916.628`

### Multi-core tuned checks

- `target=32MB`, `chunk=147456` (5x): median `3.035s`
- post-fix target probe (single-run):
  - 8MB: 3.346s
  - 16MB: 2.981s
  - 24MB: 3.016s
  - 32MB: 3.028s
- best tuned window in this pass:
  - `target=16MB`, `chunk=196608` (5x): median **`2.995s`**

### Default-mode improvement

- command: `php tempest data:parse --input-path=/workspace/data/data.csv --output-path=...`
- after heuristic retune (5x): median **`3.025s`**

### Single-core gate

- `TEMPEST_PARSER_WORKERS=1` (3 runs): median `46.114s`

## Top-3 refresh in same cycle

- current branch default (5x): `3.025s`
- PR #203 (5x): `2.856s`
- PR #3 (3x): `3.309s`
- PR #266 (3x): `3.304s`

## Keep/reject decisions in this pass

### Kept

1. buffering fix for discovery/chunk-boundary handles
2. host-aware default retune for chunk target/read chunk sizes

### Rejected

1. `TEMPEST_PARSER_ASSUME_CHALLENGE_SHAPE=1` in this cycle window (slower than default in repeated runs)
2. removing unbuffered reads inside worker chunk parsing (`parseChunk`) — clear regression in quick A/B

## Outcome

This pass improved default 100M runtime and reduced a major non-hot-loop bottleneck, but still did not produce a stable overtake of PR #203.
