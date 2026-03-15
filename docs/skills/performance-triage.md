# Performance Triage SOP

Use parser profile timings to choose the next optimization target.

## Profile command

```bash
TEMPEST_PARSER_PROFILE=1 php tempest data:parse --input-path=... --output-path=...
```

## Priority order

1. `aggregate_multi_ms` and especially `multi_read_sockets_ms`
2. merge/decode costs
3. JSON write costs

## Interpreting common outcomes

- **read_sockets dominates**: worker parse/IPC path is bottleneck (hot loop or transfer strategy).
- **merge/decode dominates**: evaluate buffer format and merge primitive.
- **write_json dominates**: reduce string concatenation/syscalls.

## Keep/reject policy

- Keep only if median improvement is repeatable and correctness holds.
- Reject noisy improvements that cannot beat baseline across repeated runs.
- Record rejected experiments with reason to avoid re-testing dead ends.
