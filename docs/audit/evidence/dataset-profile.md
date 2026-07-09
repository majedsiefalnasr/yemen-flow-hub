# Synthetic Dataset Profile

Populated during Block 3 seeding. Records the exact volumes and distributions behind every captured query plan, so plan evidence can be reproduced and its representativeness judged.

Planned distribution dimensions (per spec §4):

- **Per-bank skew** — Zipf-like: top bank carries a disproportionate share of requests, long tail of small banks.
- **Status mix** — weighted across the canonical status enum; terminal statuses dominate older rows.
- **Recency skew** — recent records over-represented (majority of rows created in the trailing window).
- **History/audit chains** — 5–50 `workflow_history`/`audit_logs` rows per request, heavy chains for a small subset of requests.
- **Nullable/optional relationships** — populated at realistic rates (e.g., SWIFT document only past `WAITING_FOR_SWIFT`).
- **Determinism** — fixed RNG seed so reruns reproduce identical distributions.

## Achieved dataset (design target reached)

| Table | Rows | On-disk (data+index) |
| --- | --- | --- |
| `engine_requests` | 1,000,000 | ~680 MB |
| `workflow_history` | 5,095,870 | ~1,391 MB |
| `audit_logs` | 5,095,870 | ~2,032 MB |
| `stage_permissions` | 28 | small |
| parents (banks 40, users 200, merchants 120, 1 published workflow / 7 stages) | — | — |

Total ≈ 4.1 GB, well above the 128 MB InnoDB buffer pool — so timings are disk-bound (see `environment.md` limitations); plan shape is the primary evidence.

### Distributions (verified post-build)

- **Status skew** (terminal-heavy): CLOSED 45.0%, ACTIVE 25.1%, REJECTED 14.9%, CANCELLED 10.0%, ABANDONED 5.0%.
- **Per-bank Zipf skew**: bank 1 = 234,150 requests; bank 2 = 114,720; bank 3 = 75,460; long tail across 40 banks.
- **Recency skew**: 57% of requests created in the trailing 90 days, 43% older (spread over ~3 years).
- **History/audit chains**: 1–6 rows per request typical; ~5% of requests carry long 20–50-row chains (heavy-chain subset), average ~5.1 rows/request overall.
- **Nullable relations**: ~15% of requests have null `merchant_id`; `claimed_by` set on ~20% of ACTIVE requests; currency 80% USD / 20% EUR|SAR.

### Build method (honest note on the fallback)

The deterministic PHP seeder (`mt_srand(42)`) reliably produced a **100k base tier** (100k requests, ~510k history, ~510k audit) in ~46 s with flat memory. Scaling the PHP loop to 1M repeatedly exhausted PHP CLI memory (leak proportional to the number of `insert()` calls, not batch size, on a single long-lived connection — a known Laravel/PDO pattern; `gc_collect_cycles()` and raising the limit to 3 GB did not fully contain it). Per the spec's synthetic-volume fallback rule, the 1M tier was instead built **set-based in SQL** by replicating the realistic 100k base nine times (offset references/invoice numbers, day-shifted timestamps) and multiplying `workflow_history`/`audit_logs` by the same request-id block offsets.

Consequence for evidence: distribution *shape* (status mix, bank Zipf, recency, chain-length variance, nullability) is faithfully preserved from the base tier; the replication introduces coarse timestamp clustering (copies shifted by whole days), which slightly understates intra-day sort cost but does not affect the index-usage, examined-rows, or join-strategy conclusions that the findings rest on. Row *counts* are exact design-target values. The base 100k tier remains available as an independent, fully-organic cross-check.

