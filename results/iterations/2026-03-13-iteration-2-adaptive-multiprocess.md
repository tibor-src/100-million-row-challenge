# Iteration 2 — adaptive multi-process parser

- **Commit:** `3c3c24c28cfaf1ff6c7db4b69f2c7d559732ac16`
- **Status:** PASS

## Changes

- Added a multi-process parser path with newline-aligned file chunk boundaries.
- Used `stream_socket_pair()` to collect worker buffers in the parent process.
- Aggregated worker counts in dense 16-bit buffers keyed by `(pathIndex, dateIndex)`.
- Switched final output generation from `json_encode()` to a manual pretty JSON writer.
- Added adaptive process selection:
  - files below `128MB` stay single-process
  - larger files default to the multi-process path
- Kept `TEMPEST_PARSER_WORKERS` as an override for explicit single-core / multi-core benchmarking.

## Validation and parity

### Validation

```bash
php tempest data:validate
```

Result: **PASS** in `0.005252123s`

### Forced multi-worker validation path

```bash
TEMPEST_PARSER_WORKERS=8 php tempest data:parse --input-path=/workspace/data/test-data.csv --output-path=/workspace/data/test-data-actual.json
cmp -s /workspace/data/test-data-actual.json /workspace/data/test-data-expected.json
```

Result: **PASS**

### Output parity checks

- 1M dataset: single-worker and multi-worker outputs matched byte-for-byte
- 10M dataset: single-worker and 4-worker outputs matched byte-for-byte

## 1M-row comparison

| Workers | Time |
|---------|------|
| 1 | 1.096921s |
| 4 | 0.887613s |
| 6 | 0.937956s |
| 8 | 0.995030s |

### Takeaway

For the smaller 1M local dataset, 4 workers are the best local setting; 8 workers add more coordination overhead than value.

## 10M-row comparison

| Workers | Time |
|---------|------|
| 1 | 6.084797s |
| 4 | 1.938152s |
| 8 | 1.927250s |
| auto | 1.930535s |

### Takeaway

For the 10M dataset, 8 workers narrowly beat 4 workers and were **3.16x faster** than single-worker mode. That justified keeping the default large-file path at 8 workers while making the parser stay single-process for smaller files.
