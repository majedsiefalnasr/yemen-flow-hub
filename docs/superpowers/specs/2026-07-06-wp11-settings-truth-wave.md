# WP-11 — Settings Truth Wave

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md` — Phase 4 SW-8 (no-placebo principle)
**Traceability:** D20-N1 (wire list), D20-N2 (mfa switch), D20-N3 (remove list), D20-N4 (SMTP decision), D20-N5 (template consolidation + R7), D20-N6 (section validation), D20-N7 (logo storage), D20-N8 (mask), D20-N9 (cache), D20-N10 (maintenance decision), D20-N11 (public settings). D13-N7 (logo) absorbed here.
**Dependencies:** WP-0; consumes WP-R R3 (PasswordPolicy) where password-expiry settings would attach (D20-N3 keeps it out until policy supports it). **Cross-package:** WP-6 reads `mfa_required`, `login_lockout_*`, remembered-device/step-up windows; ship those settings here before/with WP-6.
**Enables:** cleaner admin surface; WP-14 (template/blob cleanup).
**Overall risk:** medium — governance config; the wire/remove decisions shrink the surface before R7 consolidation.

## Change classification

| Item | Kind |
|------|------|
| ST-1 wire runtime settings | Approved functional (D20-N1/N2) |
| ST-2 remove placebo settings/flags | Migration/cleanup (D20-N3) |
| ST-3 SMTP truth decision | Approved functional (D20-N4) |
| ST-4 consolidate email templates | Migration/cleanup (D20-N5) |
| ST-5 validated settings sections | Approved functional (D20-N6) |
| ST-6 logo file storage | Approved functional (D20-N7, D13-N7) |
| ST-7 fixed password mask | Approved functional (D20-N8) |
| ST-8 consistent settings cache | Approved functional (D20-N9) |
| ST-9 maintenance mode decision | Approved functional (D20-N10) |
| ST-10 public settings safety | Confirmed (D20-N11) |

**Explicitly out of scope:** R7 settings-service architectural consolidation rides this wave (surface shrinks first); two-layer visibility (WP-7); operational monitoring (WP-13).

---

## ST-1 — Wire runtime settings (D20-N1, D20-N2)

Each setting below becomes the single runtime source; config values become bootstrap/default only.

- **`support_claim_ttl`** → `EngineClaimService` reads it (TTL + heartbeat extension). Config fallback as bootstrap.
- **`login_lockout_attempts`** (rename from `lockout_after_5_attempts` semantics; threshold) → `AuthController` reads it (WP-6 A-4 consumes).
- **`login_lockout_duration`** → lockout window (WP-6 A-4 consumes).
- **`mfa_required`** → **single runtime MFA switch** (D20-N2): login gate, profile toggle restrictions, display all read this DB setting; `config('mfa.enabled')` becomes bootstrap-only; config and DB must not silently disagree after init.
- **`pdf_upload_size_limit`** → document upload validation reads it (replaces hardcoded 10MB); applied consistently to upload endpoints + file constraints.
- **`duplicate_invoice_policy`** (`warn`/`block`) → `DuplicateInvoiceChecker` respects it (WP-7 masking respected).
- **Remembered-device duration, step-up window** (consumed by WP-6) → settings with safe defaults.

**Acceptance:** changing each setting changes the documented runtime behavior; tests verify each.

## ST-2 — Remove placebo settings/flags (D20-N3)

Remove from active UI/backend:
- **Voting/committee settings** (committee sizes, quorum, secret_voting, director_tiebreak, voting_session_timeout) — voting out of scope.
- **Legacy feature flags** (`notifications_phase_1_enabled`, `search_phase_1_enabled`, `customs_print_preview_enabled`, any no-consumer flags).
- **Unenforced security booleans** (`encrypt_uploads_aes256`, `allow_external_access`, `log_all_audit`, `password_expiry_90_days`) — hidden/removed until enforced. Password expiry, if needed, lands inside WP-6 A-3 policy; upload encryption requires real storage behavior first; `log_all_audit` removed (audit is mandatory, not a toggle).

**Acceptance:** no setting exposed without a runtime consumer; grep-clean.

## ST-3 — SMTP truth (D20-N4)

**Decision point (flagged):** keep DB-managed SMTP **only if the runtime mailer reads it**; otherwise remove the editable panel, keep read-only status/diagnostics.
**Required if kept:**
- Runtime mail delivery reads DB SMTP settings; approved-CBY-server boot guard stays; production rejects unauthorized hosts; password encrypted at rest + masked; test email uses the same runtime mail path as real sends; changes/tests audited.
**Alternative (if env-only):**
- Remove editable SMTP panel; read-only status/diagnostics view only.
**No fake SMTP panel** that only tests values without affecting real mail.
**Acceptance:** either runtime reads DB SMTP, or the editable panel is gone.

## ST-4 — Consolidate email templates (D20-N5)

- Versioned `NotificationTemplate` registry (WP-19/D19-N8) becomes the **only** active template system.
- Migrate approved/rejected/returned from the settings blob if still needed; remove blob copies.
- Rendering keeps whitelist/escaping/no-eval/versioning/audit.
**Acceptance:** one template system; no duplicate stores.

## ST-5 — Validated settings sections (D20-N6)

- workflow/security sections: define explicit fields + validation + runtime consumer + audit + admin-facing description — or remove from active UI.
- No arbitrary operational-looking JSON blobs.
**Acceptance:** every section field has a consumer + validation; raw blobs gone.

## ST-6 — Logo file storage (D20-N7, D13-N7)

- Branding logo uploaded as a file via the storage system; settings hold a reference/path/URL, not base64.
- Validate file type + size; serve via safe public URL / controlled asset endpoint; public settings stay metadata-only; cache-busting version stamp on change.
**Acceptance:** no ~3MB base64 in DB/public payload; logo served via storage URL.

## ST-7 — Fixed password mask (D20-N8)

- SMTP password masked as a fixed placeholder (`********`), never reflecting real length; secret never returned.
**Acceptance:** mask length constant.

## ST-8 — Consistent settings cache (D20-N9)

- Either runtime reads go through the cache that update invalidates, or remove the fake invalidation. No cache-theater.
**Acceptance:** cache behavior consistent (read-through or removed).

## ST-9 — Maintenance mode decision (D20-N10)

**Decision point (flagged):** real maintenance mode or remove the page.
**Required if kept:**
- Admin-controlled toggle; system banner when enabled; defined access-during-maintenance (who can still access); block/disable sensitive runtime actions if required; audited enable/disable; safe admin fallback access.
**Alternative:** remove `maintenance.vue` from navigation → WP-14.
**Acceptance:** either a real maintenance mode exists, or the page is gone.

## ST-10 — Public settings safety (D20-N11 — confirmed)

- `GET /settings/public` exposes only safe general/branding fields; never operational/security/SMTP config; version stamp for cache-busting.
**Acceptance:** no sensitive config in public payload (verify by audit).

---

## Business rules (consolidated — the no-placebo principle)

1. No active setting without an active runtime consumer.
2. MFA policy is one switch (`mfa_required`), not split between config and DB.
3. SMTP is either runtime-consumed or not editable.
4. One email-template system; validated settings sections only.
5. Secrets masked with fixed length; cache behavior consistent.

## Error cases

| Case | Response |
|------|----------|
| Setting value out of validated range/type | 422 |
| SMTP unauthorized host (production) | boot-time failure |
| Maintenance mode blocking sensitive action | 503 / configured |

## Acceptance criteria

1. Every active setting has a runtime consumer; changing it changes behavior (per-setting test).
2. `mfa_required` single switch; no config/DB split-brain.
3. Placebo settings/flags removed; grep-clean.
4. SMTP either runtime-read or panel-removed; no fake panel.
5. One template system; validated sections; logo as file; fixed mask; consistent cache.
6. Maintenance mode real or removed; public settings safe.
7. All WP-0 suites green; WP-6 settings consumed correctly (coordinate release).

## Test cases

- **Feature (per wired setting):** change setting → runtime behavior changes (claim TTL, lockout threshold/duration, MFA gate, upload size, duplicate policy, remembered-device/step-up windows).
- **Unit:** removed settings absent from API/UI; template consolidation; section validation; logo upload/serve; mask fixed length; cache consistency.
- **Regression:** existing flows that depended on hardcoded values now follow the setting (e.g. claim TTL change reflects in heartbeat expiry).

## Manual verification steps

1. Change `support_claim_ttl` → claim expiry reflects it.
2. Change lockout threshold → lockout at new value.
3. Toggle `mfa_required` → login + profile + display all agree.
4. Confirm removed settings absent from admin UI.
5. SMTP: change host → real mail uses it (or panel removed).
6. Logo: upload → served via URL, not base64.

## Rollback considerations

Wire/remove items revert per-setting. ST-4 template migration and ST-6 logo migration have data moves (non-destructive: templates copied, logo uploaded). ST-9 maintenance removal is a page-delete (revert = re-add). Coordinate with WP-6 for shared settings defaults — both packages must not assume different defaults.

## Open questions

1. **ST-3 SMTP:** is the runtime mailer able to read DB SMTP in this deployment, or is it env/config-only? Needs infra/deploy confirmation — decides keep-vs-remove.
2. **ST-9 maintenance:** real feature or remove? Needs business/ops confirmation.
3. **ST-2 `password_expiry_90_days`:** confirm it stays removed (deferred to WP-6 A-3 policy) rather than wired here.
