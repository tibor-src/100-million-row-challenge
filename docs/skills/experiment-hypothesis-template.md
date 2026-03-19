# Experiment Hypothesis Template (Tempest 100M)

Use this template for every performance experiment.

## 1) Hypothesis

- **Statement:** one sentence describing expected speed gain.
- **Target phase:** parsing / IPC / merge / JSON write / framework overhead.
- **Expected impact:** estimated range (e.g. 2–5%).

## 2) Implementation change

- exact files changed
- exact mechanism changed
- whether default behavior changed or env-gated

## 3) Correctness gates

Must pass before any benchmark claim:

1. `php tempest data:validate`
2. hash parity against trusted reference output (100M)

## 4) Benchmark evidence (mandatory)

For retained changes, include both:

- **single-core:** `TEMPEST_PARSER_WORKERS=1`
- **multi-core:** host-aware default and/or fixed tuned workers

Recommended minimum:

- 3 runs per mode (5 for key milestones)
- report median and average

## 5) Contender comparison cadence

At least one checkpoint per retained change:

- current branch vs PR #3, PR #203, PR #266 (same input file)

## 6) Decision

- **KEEP** or **REJECT**
- include concise root reason:
  - improved median with acceptable variance
  - no gain
  - regression
  - correctness risk
