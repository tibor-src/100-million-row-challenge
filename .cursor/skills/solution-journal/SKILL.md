---
name: solution-journal
description: Template and workflow for tracking parser solution iterations, benchmark results, and learnings in the 100M row challenge. Use when logging a new approach, recording benchmark results, or reviewing past attempts to plan next improvements.
---

# Solution Journal

## Purpose

Track each solution iteration with its approach, timing, and learnings.
This prevents re-trying failed approaches and helps identify which optimizations yield the most gain.

## Journal Location

Store the journal at: `.cursor/solution-journal.md`

## Entry Template

Each iteration gets an entry:

```markdown
## Iteration N: [Brief Name]

**Date:** YYYY-MM-DD
**Approach:** [1-2 sentence summary]
**Key changes from previous:**
- Change 1
- Change 2

**Technique layers used:**
- [ ] Buffered I/O
- [ ] Low-level string parsing (substr/strpos)
- [ ] Efficient aggregation
- [ ] Multi-process (pcntl_fork)
- [ ] Optimized output generation
- [ ] Advanced (lookup tables, manual JSON, etc.)

**Results:**
- Validation: PASS/FAIL
- Time (1M rows): Xs
- Time (10M rows): Xs
- Time (100M rows): Xs (if generated)
- Memory peak: XMB

**Bottleneck identified:** [What's the slowest part now?]
**Next improvement idea:** [What to try next]
```

## Workflow

1. **Before implementing:** Review previous entries to avoid repeating failed approaches
2. **After implementing:** Run validation first (`php tempest data:validate`)
3. **After validation passes:** Benchmark at 1M, then 10M rows
4. **Log results:** Add journal entry with timing and observations
5. **Identify bottleneck:** Which phase dominates? (I/O, parsing, aggregation, output)
6. **Plan next:** Based on bottleneck, pick the next optimization layer

## Improvement Decision Tree

```
Is validation passing?
├── No → Fix correctness first
└── Yes → Profile to find bottleneck
    ├── I/O bound → Try larger buffers, fread blocks, or parallel reads
    ├── Parse bound → Optimize string ops (substr vs explode, avoid allocations)
    ├── Aggregation bound → Optimize data structure, reduce hash lookups
    ├── Output bound → Optimize JSON generation, buffered writes
    └── CPU bound overall → Add multi-process parallelism
        ├── Already parallel → Tune worker count, chunk sizes
        └── Already tuned → Try advanced: manual JSON, lookup tables, packed arrays
```

## Comparison Format

When comparing iterations:

```markdown
| Iteration | Approach | 1M time | 10M time | Key change |
|-----------|----------|---------|----------|------------|
| 1 | Baseline fgets | Xs | Xs | - |
| 2 | fread blocks | Xs | Xs | Buffered I/O |
| 3 | + substr parsing | Xs | Xs | String ops |
| 4 | + pcntl_fork x4 | Xs | Xs | Parallelism |
```
