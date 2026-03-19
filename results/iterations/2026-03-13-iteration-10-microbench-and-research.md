# Iteration 10 — microbench-first function analysis and online research

- **Base commit:** `ce83bb43ce132cd5cd8f82dbf68e4f7cb13dcde6`
- **Status:** in progress

## Request focus

1. run smaller isolated function experiments (1M+ style loops)
2. research optimization advice online and test locally
3. keep single-core and multi-core parser evidence in each retained cycle

## Microbench harness

Added:

- `results/microbench/function_call_bench.php`
- outputs:
  - `results/microbench/function-call-bench-latest.json`
  - `results/microbench/function-call-bench-repeat.json`

## Function-level outcomes (synthetic)

### 1) read-size choice
- branch (`$remaining > $chunk ? $chunk : $remaining`) beat `min()`
- result stable across two harness runs

### 2) short-date extraction
- `substr(..., 7)` decisively beat manual character concatenation

### 3) byte increment
- lookup table beat `ord()+chr()` update

### 4) map lookup style
- null coalescing lookup beat explicit `isset`+index in this setup

### 5) newline search
- `strrpos` beat manual reverse scan loop

## Online research checkpoints

Queried and cross-checked:

- `min()/max()` function-call overhead in tight loops
- substring extraction practices in PHP
- socket read strategy around `stream_select` and `stream_get_contents`

Findings were directionally consistent with local synthetic harness.

## Parser-level translation attempts

### Attempted change
- translated selected function-level ideas into parser path experiments.

### Observed reality
- parser-level end-to-end 100M timings remained noisy in this environment.
- some seemingly good microbench changes did not produce a stable parser win.

## Mandatory parser-mode checks

Single-core and multi-core checks were executed in this pass as required.

## Top3 comparison cadence

Regular comparisons against PR #3, PR #203, PR #266 were run.

Mixed outcome:

- one head-to-head batch against PR #203 favored current branch
- follow-up batch favored PR #203

No stable overtake conclusion can be claimed from this iteration yet.

## Keep / reject summary

### Keep (as decision guidance)
- retain `substr` over manual char concatenation
- retain lookup-table byte increment
- retain `strrpos` newline search
- retain select-based multi-socket reading as default mode

### Reject / not retained as parser default yet
- direct parser substitution of microbench branch-for-min in hot chunk reads (no stable end-to-end gain shown in this run window)
- any regression-causing parser-level changes from this cycle
