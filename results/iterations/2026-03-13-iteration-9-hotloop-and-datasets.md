# Iteration 9 — hot-loop redesign, socket-mode A/B, and multi-dataset matrix

- **Base commit:** `43b5547427f8fffe451f250dc638e7ba8c393cee`
- **Status:** PASS (validation + hash parity preserved)

## Goals

1. improve PHP-only hot path without FFI/setup changes
2. run mandatory single-core and multi-core checks for retained work
3. compare regularly against top 3
4. run multiple generated datasets and compare against top 3
5. codify experiment workflow skills/SOPs

## Hypotheses tested

### H1 — Packed worker chunk handling without carry concatenation is faster

**Change:** moved packed worker parsing to newline-rewind chunk processing (avoids `$carry . $chunk` rebuild in hot path).

**Result:** retained. 100M multi-core moved from iteration-8 range (~3.15s median) to ~2.95s class with follow-up tuning.

### H2 — Manual 10-step unroll in trusted packed loop is faster than runtime `for` loop

**Change:** replaced looped unroll in trusted packed parser with explicit repeated operations.

**Result:** retained. measurable additional reduction in multi-core runtime.

### H3 — Concurrent socket reads outperform sequential stream drains

**Change:** added selectable socket read mode and A/B tested:

- `select` mode (`stream_select` + incremental `fread`)
- `sequential` mode (`stream_get_contents` per socket)

**Result:** retained `select` default (`2.930s` median vs `2.950s`).

### H4 — Trust mode must be gated by sampled date-shape to avoid crashes on non-challenge seeds

**Problem observed:** generated seed `12345` produced 1960s dates; trusted fast path crashed workers.

**Change:** trust-fast-path now also requires sampled discovery lines to match fast-path short-date map.

**Result:** retained. no more worker crash on out-of-range seed; parser falls back safely.

## Workflow skill improvements added

Created SOP docs:

- `docs/skills/experiment-hypothesis-template.md`
- `docs/skills/benchmark-matrix.md`
- `docs/skills/performance-triage.md`

These now define mandatory evidence and keep/reject rules.

## 100M benchmark highlights

### Current branch

- single-core sample: ~`41.84s`
- multi-core tuned (`workers=8,target=36MB,chunk=144KB`): **`2.9467s` median** (5 runs)

### Top-3 refresh (3-run)

- current branch: `2.9806s` median
- PR #203: `2.8118s` median
- PR #3: `3.2655s` median
- PR #266: `3.1670s` median

Current branch remains behind PR #203.

## Multi-dataset matrix (20M seeded)

Generated:

- `/tmp/data-seed-1772177204.csv`
- `/tmp/data-seed-1772500000.csv`

For each dataset:

- current branch single-core + multi-core
- PR #3, PR #203, PR #266

Findings:

- current branch scales strongly from single to multi
- PR #203 still leads on these additional seeds

## Rejected / non-retained outcomes

1. reducing packed index bit width to 20 (`PACKED_INDEX_BITS=20`) did not produce a stable win
2. trust mode on out-of-range date seeds without guard produced worker failures
3. decoding merged dense buffer via `unpack('v*')` regressed 100M multi-core runtime and was reverted

## Mid-iteration bug fix note

An initial implementation of date-shape trust gating used an incorrect short-date substring offset during discovery checks, which suppressed trust mode on challenge-shaped data. This was corrected to match packed parser short-date extraction.

## Current conclusion

Iteration 9 delivered a major compliance-path gain versus iteration 8 and hardened behavior on non-challenge seeds. We are now close to PR #203 but still not ahead in repeated 100M comparisons.
