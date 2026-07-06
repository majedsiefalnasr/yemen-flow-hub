# WP-14 — Legacy Cleanup Wave (Terminal)

**Status:** Draft for review (Phase 6) — **TERMINAL package**
**Source of authority:** `2026-07-05-feature-review-notes.md` — D23-N1..N13
**Traceability:** D23-N3 (consumer migration), D23-N2 (demo route removal), D23-N4 (audit widget migration), D23-N5 (dead-code purge), D23-N6 (dropped-table refs), D23-N7 (column drop — feeds from WP-10 RM-3), D23-N8 (committee_director — from WP-10 RM-5), D23-N9 (settings/placebo — from WP-11), D23-N10 (namespace policy), D23-N11 (presets — from D18-N7), D23-N12 (reset-pin — from WP-10 RM-7), D23-N13 (cleanup sequence), R9 (API envelope staged migration).
**Dependencies:** WP-10 (RM-3 column drop complete) + all consumer-migration prerequisites. **Runs last.**
**Enables:** clean codebase; one API namespace direction.
**Overall risk:** medium — sequencing-managed; the strict sequence (D23-N13) is the risk control.

## Change classification

| Item | Kind |
|------|------|
| L-1 V1 replacements | Migration/cleanup (D23-N4, D23-N11) |
| L-2 frontend consumer migration | Migration (D23-N3) |
| L-3 demo route production removal | Approved functional security (D23-N2) |
| L-4 dead-code purge | Migration/cleanup (D23-N5) |
| L-5 dropped-table reference purge | Migration/cleanup (D23-N6) |
| L-6 staged API envelope migration (R9) | Refactor / contract change (SW-9) |
| L-7 regression + release notes | Process |

**Explicitly out of scope:** any feature work (all in earlier WPs); this package only removes/migrates once dependencies are met.

---

## Authoritative sequence (D23-N13)

1. Build missing V1 replacements (L-1).
2. Update frontend consumers (L-2).
3. Update composables (L-2).
4. Update tests (per migration).
5. Verify zero legacy traffic/consumers remain.
6. Remove legacy routes/controllers/files (L-4/L-5).
7. Stage API envelope migration (L-6) — endpoint-by-endpoint, behind the wave.
8. Full regression (L-7).
9. Release notes list removed endpoints/modules.

**Never delete a live legacy endpoint before its consumer is migrated + verified.**

---

## L-1 — V1 replacements

Build any missing V1 endpoint before retiring its legacy source:
- **Audit stats / risk-indicators (D23-N4):** review usefulness on `audit.vue`; rebuild as V1 under audit/compliance namespace with WP-7 two-layer visibility if useful; remove stale panels if not.
- **Report presets (D23-N11 / WP-18-N7):** promote to V1 as user-scoped saved filters (if retained); never bypass data scope.

## L-2 — Frontend consumer migration (D23-N3)

Migrate live consumers to V1/current canonical endpoints:
- `/api/users` → `/v1/users` (`staff.vue`, `AccountRecoveryDialog.vue` via `useUsers`).
- `/api/banks` → `/v1/banks` (`admin/banks.vue`, `merchants.vue` via `useBanks`).
- `/api/audit*` → `/v1/audit-logs` + compliance endpoints (`audit.vue` via `useAudit`).
- `/api/report-presets` → V1 presets (L-1) via `useReports`.
- Legacy reset-pin → V1 (WP-10 RM-7 already built) — consumer updated.
Update composables + tests alongside.

## L-3 — Demo route production removal (D23-N2)

- Demo routes (`auth/demo-users`, `switch-demo-user`, `switch-demo-role`) registered **only** when `APP_ENV` explicitly allowed (local/staging); production has **no route**, not a disabled one.
- `auth/demo-users` never unauthenticated in production-like envs.
- Every demo switch in allowed envs audited (actor, target, timestamp, IP, environment).
- `.env.example` stays `APP_DEMO_ROLE_SWITCH=false`.

## L-4 — Dead-code purge (D23-N5)

Remove together (routes/controllers/composables/tests/UI links — no half-removed modules):
- Legacy `NotificationController` routes (frontend on `/v1/notifications`).
- Legacy `ReportController` (incl. dead voting reports).
- Legacy `MerchantController` file (no route registered).
- `document-types` module (controller/routes/model/`useDocumentTypes`/tests — D10-N8 confirmed dead).
- Legacy `/requests` tests + stale frontend references (D3-N9).
- Dead simplified-status constants + route-role maps (D3-N4, after FX terminology cleanup).
- Stale customs route-role maps (post-WP-8 D11-N6/N7).
- Stale `/requests/{id}` notification URL generation (post-WP-0 BF-6 / D19-N1).
- `users.role` column (WP-10 RM-3 complete) + `legacyRoleFor` shim + `UserRole` enum if unused.
- Stale `committee_director` gates (WP-10 RM-5 complete).
- Placebo settings/flags (WP-11 ST-2 complete).
- Duplicate email-template blob (WP-11 ST-4 complete).
- `engine_claim:` cache mirror (WP-5 CL-7 complete).
- `stageIsBound` / `fieldIsUsed` dropped-table checks (L-5).

## L-5 — Dropped-table reference purge (D23-N6)

Remove/rewrite all `ImportRequest` / `import_requests` references and wrong-table guards:
- `stageIsBound` (designer) + `fieldIsUsed` (field designer) — already fixed where fatal (WP-0 BF-1); sweep remaining wrong-table checks.
- Any bank/user/request guard checking dropped structures → engine request / workflow-version references.
- Regression tests on previously failing cleanup paths.

## L-6 — Staged API envelope migration (R9 / SW-9)

**Controlled, not big-bang.** Standardize on the rich envelope (`error.code`, `error.message`, `error.fields`, `request_id`); keep stable business codes (incl. engine codes); standardize pagination meta.
- During L-2 migrations (endpoints being rewritten anyway), new/V1-migrated endpoints adopt the rich envelope.
- Frontend error extraction becomes one utility tolerating both shapes.
- Legacy shapes converted last, endpoint-by-endpoint, behind this wave.
- Transition period / adapter if compatibility needed; **never bundled with functional fixes**.

## L-7 — Regression + release notes

- Full backend + frontend regression at wave end (broad refactor → full suites justified).
- Release notes list every removed endpoint/module + any contract change (envelope, removed `bank_id` payload field from WP-1, new statuses from WP-2, etc.).

---

## Business rules (consolidated)

1. Migration-first, removal-second — never break a live screen.
2. Demo/impersonation routes absent from production entirely.
3. No dropped-table references remain; no dead modules half-removed.
4. API envelope standardized via staged migration, preserving stable codes.
5. Release notes capture every removal + contract change.

## Error cases

| Case | Response |
|------|----------|
| Removed endpoint hit by stale client | 404 (route gone) |
| Demo route in production | route absent (404) |
| Legacy envelope client after migration | adapter tolerates during transition |

## Acceptance criteria

1. Every live consumer migrated to V1; zero traffic on legacy routes (verify via logs/grep).
2. Demo routes absent from production builds.
3. Dead-code purge complete (grep-clean for each purged item).
4. No dropped-table reference remains; wrong-table guards gone.
5. API envelope standardized on migrated endpoints; frontend extraction unified; stable codes preserved.
6. Full regression green; release notes published.
7. All WP-0 suites green; no earlier-WP behavior regressed.

## Test cases

- **Regression (full suites):** backend + frontend green post-purge.
- **Grep verification:** no purged literal/route/file remains in `app/`/`frontend/` (outside intentional historical references like audit snapshots).
- **Contract:** migrated endpoints return the standardized envelope; demo routes 404 in production-equivalent env.

## Manual verification steps

1. staff/banks/audit/reports screens work end-to-end on V1 endpoints.
2. Production-equivalent env: demo routes 404.
3. No 404s/500s from stale references across a full engine flow.
4. Error responses use the standardized envelope on migrated endpoints.

## Rollback considerations

Terminal package — rollback is per-removed-item (re-add route/controller). Envelope migration rollback = adapter keeps tolerating legacy shape. Because this runs last and behind full regression, rollback risk is contained to the removal commits. Coordinate release with all earlier WPs landed.

## Open questions

1. **L-1 audit widgets:** keep + rebuild (audit stats, risk indicators) or drop the panels? Needs product confirmation on whether operators use them.
2. **L-6 envelope timing:** stage within WP-14 (recommended — endpoints being rewritten anyway) or a separate dedicated wave after WP-14? Recommend within WP-14.
3. **L-3 demo env gate:** `APP_ENV` whitelist (local + staging) — confirm staging is desired or local-only.
