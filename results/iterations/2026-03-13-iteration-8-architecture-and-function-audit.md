# Iteration 8 — architecture/function audit and contender parity checks

- **Commit:** `651c8cba60e8b9768c79e2b990cb058e4cf691a7`
- **Status:** PASS

## What was requested in this pass

1. check PHP architectural options (SPL/static structures) for memory/CPU efficiency
2. inspect function-level parameter differences between our parser and top contenders
3. analyze implications for larger machines (up to challenge-target class devices)
4. verify whether contenders changed `DataParseCommand` invocation style

## Contender parity checks

### `DataParseCommand` invocation style

Checked:

- `/tmp/top3-bench/pr3-xheaven/app/Commands/DataParseCommand.php`
- `/tmp/top3-bench/pr203-acidburn86/app/Commands/DataParseCommand.php`
- `/tmp/top3-bench/pr266-random767435/app/Commands/DataParseCommand.php`

Result:

- all three call `new Parser()->parse(...)` from command class
- parser methods remain static in all three

Action taken:

- switched our command invocation back to `new Parser()->parse(...)`

## Function/parameter comparison and retained change

### Notable contender function usage

- `stream_set_chunk_size()` on worker sockets (PR #3, PR #203)
- `stream_select()` + incremental socket reads (PR #3, PR #203)
- `stream_get_contents()` for socket payload (PR #266)
- `chunk_split(..., 1, "\0")` + `sodium_add()` merge (PR #203)

### Our retained IPC changes

1. set worker socket chunk size based on expected payload (`stream_set_chunk_size`)
2. replaced parent `fread` loop with `stream_get_contents` in `readSocket()`

## Full-size benchmark impact (100M)

### Profiled run

`TEMPEST_PARSER_PROFILE=1 php tempest data:parse ...`

- wall time: `3.116s`
- `aggregate_multi_ms`: `2860.200`
- `total_parse_ms`: `2916.663`

### Unprofiled median

5-run set:

- `3.100s`
- `3.144s`
- `3.146s`
- `3.184s`
- `3.190s`

Median: **`3.146s`**

## Architectural option checks (SPL/static structures)

Synthetic counter microbench:

- slots: `268 * 2191`
- operations: `20,000,000` increments

Results:

- packed array (`array_fill`): `~0.232s`, `~18MB` peak
- `SplFixedArray`: `~0.342s`, `~11MB` peak
- byte-string buffer: `~0.342s`, `~2MB` peak

Interpretation:

- packed arrays can be faster in isolation but cost much more memory per worker and larger IPC payloads
- `SplFixedArray` saves memory but did not improve throughput here
- byte strings still provide the best memory density for scaling worker count and keeping merge payload compact

## Larger-machine analysis (toward challenge-class hardware)

Current defaults are now host-aware and scale beyond this 4-core VM:

- worker selection tied to detected CPU count, capped at 16
- chunk target derived from file size and worker count (`8MB..32MB`)
- read chunk size tiers by file size
- trust-fast-path auto-enabled only for large files

Spot checks on this machine still indicate:

- 8 workers remains the strongest practical baseline
- higher worker counts (10/12/16) do not improve this host

But on larger-core hardware, the memory profile of byte buffers keeps per-worker cost low enough to support higher concurrency without the array-structure penalty.
