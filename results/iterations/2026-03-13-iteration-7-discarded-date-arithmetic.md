# Iteration 7 — discarded direct-date-indexing attempt

- **Base commit:** `36fc2a6c571a9520da67984fd5f7228c1558efb3`
- **Status:** reverted / not retained

## Hypothesis

The trusted packed hot loop might speed up by replacing date-key hash lookups (`$dateIdsShort[substr(...)]`) with direct ASCII arithmetic against fixed date positions.

## Experiment

Implemented direct date-index arithmetic inside trusted packed loop and reran full 100M parse.

## Outcome

- **Wall time:** ~`5.573s` (single run)
- **Correctness:** output hash still matched reference
- **Decision:** reverted due to large performance regression

## Interpretation

Although the arithmetic approach removed one hash lookup, the extra `ord()` operations and branching in the hot loop performed worse in this PHP runtime profile than the existing short-date-key map lookup.
