# Iteration 12 — continued stability retries with versioned commits

## Scope

Continue with all known approaches, run repeated stability sets (5x), and commit each version.

## Versioned parser changes

### 1) `eadee40` — shared handle retry

- reused worker file handle across chunk loop
- precomputed helper data once per worker
- **result:** regression in 5x multi-core set

### 2) `d55602f` — revert shared-handle retry

- reverted to previous stable behavior

## Stability sweeps executed

### Single-core / Multi-core 5x

- single-core remained high-40s seconds range
- multi-core remained around low-3s range in this run window

### Config sweeps (5x each where applied)

- chunk target: 16MB / 24MB / 32MB
- merge mode: sodium vs manual
- socket mode: select vs sequential
- worker counts: 6 / 7 / 8

## Reconfirmed best-known local combo in this pass

- merge: `sodium`
- socket read: `select`
- workers: `8`
- chunk size around `147456`
- chunk target in tested window: `32MB` performed best among tested values

## Head-to-head status

Paired 5x windows against PR #203 still showed PR #203 median advantage.

## Final outcome for this iteration

No stable repeated overtake of PR #203 yet; experimentation remains active with documented regressions and reversions.
