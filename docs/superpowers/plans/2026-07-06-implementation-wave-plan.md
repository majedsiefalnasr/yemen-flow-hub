# Implementation Wave Plan — Yemen Flow Hub Review → Specs → Execution

**Status:** Approved 2026-07-06 — **Wave 5 ✅ complete (WP-11 + WP-12 + WP-13 merged to main); next: Wave 6 / WP-14**
**Authority:** the 15 approved work-package specs under `docs/superpowers/specs/2026-07-06-wp*` + `2026-07-06-wp0` / `wpr`; phase record in `2026-07-05-feature-review-notes.md`.
**Purpose:** collapse 14 implementation specs into 6 execution waves that preserve dependency safety while letting parallel-capable packages run together.

## Hard dependency rule (never violated by wave grouping)

Multiple WPs may be developed in parallel; **merge order always respects dependencies**:
- WP-0 before everything.
- WP-R before heavy controller/store changes.
- WP-1 before WP-7.
- WP-3 before WP-4 (even inside the same wave — sequential).
- WP-4 and WP-7 before WP-8 (WP-7 starts first inside its wave).
- WP-10 RM-3 (column drop) deferred to verification window before WP-14.
- WP-14 last; no removals before earlier packages ship + consumers migrate.

## Waves

### Wave 1 — Safety + mechanical groundwork
**WPs:** WP-0, WP-R. **Internal order:** WP-0 → WP-R.
- Creates the test baseline (5 characterization/regression suites) + 6 confirmed bug fixes (WP-0).
- Behavior-preserving refactors + audience/normalization/password/role-code consolidation (WP-R).
- Reduces collision risk before any functional change. **Must not be skipped.**
- **Gate:** WP-0 green + WP-R `route:list`/snapshot equivalence proven before Wave 2 starts.

### Wave 2 — Core workflow model
**WPs:** WP-1, WP-2, WP-3, WP-4. **Internal order:** WP-1 → WP-2 → WP-3 → WP-4.
- Classification foundation (WP-1), outcome semantics (WP-2), designer validation pack (WP-3), semantic metadata mechanism (WP-4).
- Reshapes the workflow model + publish/runtime rules.
- **Gate:** WP-4 semantic resolver + publish validation land before Wave 4's WP-8 consumer work.

### Wave 3 — Runtime enforcement + auth/security ✅ COMPLETE
**WPs:** WP-5, WP-6, WP-9, WP-10. **Branch:** `feat/wp9-governance-lifecycle-guards` (integration commit `a410cf35`).
- Track A: WP-5 Claims
- Track B: WP-6 Auth hardening
- Track C: WP-9 Governance lifecycle guards
- Track D: WP-10 Role model migration
- **Constraint:** WP-10 RM-3 (`users.role` drop) stays deferred — verification window first.
- **Cross-track coordination:** WP-6 consumes WP-11 auth settings (mfa_required, lockout) — if Wave 5 hasn't shipped them, WP-6 uses fallback defaults; align defaults across both waves.

### Wave 4 — Visibility + documents + FX (highest-risk product wave) ✅ COMPLETE
**WPs:** WP-7 ✅, WP-8 ✅. **Internal order:** WP-7 → WP-8 (both merged to `main`; WP-8 merge commit `00fe8655`).
- WP-8 hard-depends on WP-4 (semantic mapping) + WP-7 (DataScope/output visibility).
- WP-8 notes: F-16 terminology verified already-shipped (no new commit); F-8 scanning shipped schema-ready with enforcement config-gated; F-14 official-issuer source (`issued_by`) remains an open business question — `generated_by` carries the transition actor.

### Wave 5 — Runtime UX + settings + operations ✅ COMPLETE
**WPs:** WP-11 ✅, WP-12 ✅, WP-13 ✅. **Parallel tracks (all merged to `main`):**
- Track A: WP-11 Settings truth wave ✅ (`e02cce5d`)
- Track B: WP-12 Runtime UX pack ✅ (merge includes stats, confirmations, audit diffs, export UX)
- Track C: WP-13 Retention + operations ✅ (`aa2767e0` — retention jobs, audit archive, admin health, runbook)
- **Merge note:** WP-12 merged before WP-13; export stack reconciled (`FAILED` + `EXPIRED` + ops logging)
- **Constraint:** WP-6 auth defaults canonical via WP-11: `mfa_required=false`, lockout 5/15 min

### Wave 6 — Terminal cleanup
**WPs:** WP-14.
- Runs strictly last; no removals before earlier WPs ship + consumers migrate.
- Stages the R9 API envelope migration endpoint-by-endpoint within this wave.

## Execution start

Implementation begins with **Wave 1 / WP-0** (safety net tests + confirmed bug fixes), then WP-R. No later package work until Wave 1 gates pass.

## Notes

- Each WP spec is the authoritative acceptance source for its package; this plan only sequences them.
- Wave grouping speeds execution but does **not** relax any per-WP scope fence, do-not-touch constraint, or test-first requirement.
- Where a WP defers an item (WP-10 RM-3, WP-8 F-8 scanning, WP-13 destructive purge pending CBY policy), the deferral is tracked in that spec's open questions — not dropped.
