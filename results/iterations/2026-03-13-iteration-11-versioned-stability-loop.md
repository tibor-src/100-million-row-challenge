# Iteration 11 — versioned stability loop (5x gates)

## Goal

Continue all known approaches while:

1. committing each meaningful parser version
2. using repeated stability runs (5x) to reduce one-off noise
3. checking both single-core and multi-core modes
4. comparing against PR #203 regularly

## Versions tested and outcomes

### `dae06b7` — shared worker file handle parsing

- idea: reduce repeated open/close overhead by reusing worker handle
- result: multi-core median regressed in 5x set
- status: rejected

### `57e2a5c` — split packed and generic chunk paths

- idea: reduce branch overhead and simplify chunk parser paths
- result: no stable gain; still slower than required target
- status: rejected

### `086c567` — revert regressive worker-handle experiments

- restored prior behavior with better stability than rejected variants

### `e15ff2a` — assume challenge-shape mode toggle

- idea: allow optional aggressive fast-path assumptions
- result: mixed; occasional gain in paired windows but unstable and not a reliable default win
- status: optional toggle only, not promoted as default strategy

### `83bf560` — worker socket write fast-path

- idea: reduce write-loop overhead with first full-buffer write before fallback loop
- result: slight improvements in some windows, but still not enough to overtake PR #203 stably
- status: retained pending further evidence

### `8b36d6b` — split safe/trusted packed consumers

- idea: reduce per-call dispatch in packed consumption
- result: clear regression
- status: rejected, reverted by `e90e635`

### `e90e635` — revert consumer split regression

- recovered prior performance envelope.

## 5x stability findings (current tuned path)

- worker count sweep: `w8` remains best in this environment
- merge mode: sodium > manual
- socket mode: select > sequential
- current tuned median remains around low 3.0s in recent windows

## Head-to-head result in this cycle

5x comparison window:

- current tuned branch: ~`3.019s` median
- PR #203: ~`2.890s` median

No stable overtake yet.

## Single-core checks

Single-core runs continued in this cycle for every retained direction, with wall times in the high-40s seconds range under current host conditions.
