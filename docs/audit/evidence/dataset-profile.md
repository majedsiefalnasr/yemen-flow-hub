# Synthetic Dataset Profile

Populated during Block 3 seeding. Records the exact volumes and distributions behind every captured query plan, so plan evidence can be reproduced and its representativeness judged.

Planned distribution dimensions (per spec §4):

- **Per-bank skew** — Zipf-like: top bank carries a disproportionate share of requests, long tail of small banks.
- **Status mix** — weighted across the canonical status enum; terminal statuses dominate older rows.
- **Recency skew** — recent records over-represented (majority of rows created in the trailing window).
- **History/audit chains** — 5–50 `workflow_history`/`audit_logs` rows per request, heavy chains for a small subset of requests.
- **Nullable/optional relationships** — populated at realistic rates (e.g., SWIFT document only past `WAITING_FOR_SWIFT`).
- **Determinism** — fixed RNG seed so reruns reproduce identical distributions.

## Achieved dataset

(filled after seeding; includes final row counts per table, distribution parameters actually used, seeding duration, storage size, and — if the 1M+ goal was not reached — the limiting resource and how that weakens the evidence)
