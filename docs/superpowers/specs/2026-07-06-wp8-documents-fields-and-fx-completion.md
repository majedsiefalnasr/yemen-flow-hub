# WP-8 — Documents, Fields, and FX Completion

**Status:** Draft for review (Phase 6) — large package (cohesive: everything data-shape + evidence + FX deliverable)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D6-N1 (scoped options), D6-N2/N3 (option membership + grandfathering), D6-N7/N8 (FILE + typed values), D10-N2 (required-file evidence), D10-N3+D3-N2 (field visibility on output/documents), D10-N4 (scanning), D10-N5 (checksums), D10-N7 (replacement flow), D11-N1 (FX panel rebuild), D11-N2/N3/N5/N9 (FX replacement/auth/semantics/immutability), D11-N6/N7 (terminology + placement), D22-N1 (financing advisory UI). Uses WP-4 semantic resolver + WP-7 DataScope.
**Dependencies:** WP-4 (semantic mapping for FX PDF fields + financing validation) **hard**; WP-7 (DataScope for option scoping + output visibility + advisory) **hard**; WP-0 BF-6 (notification URLs fixed); WP-5 CL-3 (document claim gating already shipped).
**Enables:** nothing downstream blocked.
**Overall risk:** medium-high — broad but the pieces are independent; FX and financing UI are restorations of a known-good pre-cutover design.

## Change classification

All items: **approved functional changes** (D-notes) except D11-N6 terminology (migration/cleanup) and the malware-scan infrastructure piece (D10-N4, may defer to infra readiness).

**Explicitly out of scope:** reference-data lifecycle guards (WP-9); field-rule publish validation (WP-3, already shipped); server-side list redesign (WP-12); two-layer scope (WP-7, ships the primitive this consumes).

---

## Part A — Dynamic field options

### F-1 — Bank-scoped merchant/company options (D6-N1)

**Current:** `DynamicFieldOptionsResolver` returns all merchants/companies globally (no context).
**Required:** resolver takes context (actor + request bank); MERCHANTS/MERCHANT_COMPANIES scoped to: bank users → own bank; CBY/NC on an existing request → the request's bank; creation → creator's bank. Global lists only in admin screens. Uses WP-7 `DataScope`.
**Acceptance:** bank user filling a MERCHANTS field sees own-bank merchants only; NC viewer sees the request's bank.

### F-2 — SELECT/DYNAMIC_SELECT membership validation (D6-N2)

**Current:** `StageFieldRuleValidator::checkConstraints` covers regex/number/length/file only — select values never checked against options.
**Required:** add select-membership check: static SELECT → configured options; DYNAMIC_SELECT → resolved set in correct context (F-1). Reject unknown values with field error. Applied in draft save + transition + any data-accepting endpoint.
**Acceptance:** submitting a value not in the option set → 422 field error.

### F-3 — Grandfathering for deactivated option values (D6-N3)

**Required:** stored values valid at selection time stay readable; deactivated options unavailable for new selection; editing the field later forces an active choice; existing requests never break; UI shows old stored label marked inactive/deprecated where possible.
**Acceptance:** a request with a since-deactivated reference value still renders; editing forces re-selection.

### F-4 — FILE field references server-validated documents (D6-N7)

**Current:** FILE field `data` value = client-asserted `{mime, size_kb}` metadata; real validation only at documents endpoint.
**Required:** FILE field value stores a **reference** to an uploaded `EngineRequestDocument`; validator verifies the referenced document exists, belongs to the same request, matches allowed type/size. Client metadata never accepted as proof. `DynamicFormField` FILE rendering reflects this (upload → reference).
**Acceptance:** FILE field with no linked document → required check fails; client metadata alone → rejected.

### F-5 — Typed value validation DATE/CHECKBOX/default (D6-N8)

**Required:** DATE = ISO-8601 (`YYYY-MM-DD`), validated at draft save + transition; CHECKBOX stores booleans only; `default_value` validated per field type.
**Acceptance:** bad date format → 422; non-boolean checkbox → 422.

---

## Part B — Field visibility on output

### F-6 — Output respects field visibility (D3-N2, D10-N3)

**Current:** `is_visible` enforced on input only; `show`/list return full `data` JSON to any VIEW-holder; documents linked to hidden fields listable/downloadable.
**Required:**
- `EngineRequestResource` / `formSchema` / list responses filter `data` by per-stage visibility rules for the **viewer** (hidden fields omitted or masked).
- Field-linked documents (`field_id`) respect the owning field's visibility for list/download (hidden field ⇒ document not listed/downloadable for that viewer).
- Unlinked documents keep the general stage/request document policy.
- Applies to detail, list, exports.
**Acceptance:** viewer without visibility on a field sees neither its value nor its linked documents.

---

## Part C — Document integrity

### F-7 — Required FILE fields need real documents (D10-N2)

**Required:** leaving a stage with a required FILE field requires ≥1 non-deleted document linked to that field, same request, matching constraints. Enforced in transition validation (leaving-stage). Ties to F-4.
**Acceptance:** required FILE field without a linked document → transition blocked `STAGE_FIELDS_INVALID`.

### F-8 — Malware/antivirus scanning (D10-N4)

**Required:** uploads scanned before treated as available; infected/suspicious rejected/quarantined; async scan gets a scan status (`pending/clean/infected/failed`) on `EngineRequestDocument`; downloads only for `clean` files. **Subject to infrastructure readiness** — if the environment can't support now, recorded as high-priority security hardening with the status field added schema-ready.
**Acceptance (if infra ready):** infected upload → rejected/quarantined; pending file → not downloadable.

### F-9 — Checksum verification (D10-N5)

**Required:** verify stored sha256 on download (and/or scheduled integrity job); failure blocks download + raises audit/security event.
**Acceptance:** tampered/corrupted file → download blocked, audited.

### F-10 — Document replacement flow (D10-N7)

**Current:** delete soft-removes; return-loop re-makes prior-visit documents deletable.
**Required:** controlled replacement — old document marked `superseded` (metadata + audit kept), replacement stored as new record, old↔new linked, replacement audited (who/when/why). Active document = latest non-superseded, non-deleted. Physical cleanup only via documented retention policy (WP-13), never user delete. Prior-visit documents never freely re-deletable on stage re-entry.
**Schema:** `engine_request_documents.superseded_by` (nullable FK to self) + status.
**Acceptance:** replace flow preserves history; old file retained per policy; no silent removal.

---

## Part D — FX confirmation completion

### F-11 — FX panel rebuild (D11-N1)

**Current:** signed-FX upload + both download endpoints have zero frontend consumers (deleted in `0e9f1eae`).
**Required:** rebuild as engine component `components/workflow/EngineFxConfirmationPanel.vue` mounted on `/workflows/instances/[id]` when a declaration exists / request at/past FX stage; wires to V1 endpoints. Old `FxConfirmationCard.vue` (recoverable via `git show 0e9f1eae^:`) is reference only.
**Acceptance:** Director can upload signed FX; banks can download the deliverable.

### F-12 — FX authorization via stage permissions (D11-N3)

**Current:** hardcoded role-code gates (`committee_director`, etc.) in `FxConfirmationUploadRequest` + `EngineCustomsService` + `CustomsDeclarationPolicy`.
**Required:** signed-FX upload = EXECUTE on the FX confirmation stage; artifact download = VIEW + request scope (DataScope). `system_admin` oversight exception retained. No hardcoded role-code gates. Backend final enforcement.
**Acceptance:** FX stage executor can upload; non-executor cannot; bank users download own-bank deliverable.

### F-13 — FX signed-doc replacement (D11-N2)

**Required:** replacement allowed; old physical file may be deleted/archived per retention (storage-friendly) **but** metadata/checksum/uploader/timestamps/replacement-actor/audit/reason preserved. Active = latest; audit history shows replacement. (Specializes F-10 — physical retention relaxed for signed FX; audit not.)
**Acceptance:** replace signed doc → audit trail intact; old file removed per policy.

### F-14 — FX issuance semantics split (D11-N5)

**Required:** `generated_by` (transition trigger actor), `issued_by` (official business issuer), `signed_uploaded_by` (signed-doc uploader) — separate fields. Transition actor ≠ official issuer unless business confirms.
**Schema:** add `generated_by`, `signed_uploaded_by` to `customs_declarations`; `issued_by` repurposed/clarified.

### F-15 — FX immutability model fix (D11-N9)

**Current:** `CustomsDeclaration::booted()` blocks all updates; `EngineCustomsService` bypasses via `DB::table`.
**Required:** declaration immutable for official issued fields; signed-doc fields explicitly whitelisted mutable; no broad `DB::table` bypass; signed-doc mutations always audited.
**Acceptance:** signed-doc upload updates only whitelisted columns; official fields immutable.

### F-16 — FX terminology + placement (D11-N6, D11-N7)

**Required:** user-facing labels renamed customs declaration → External FX Confirmation; legacy terminology tracked as cleanup; existing records/files unbroken; `CD-` number-prefix migration reviewed separately. `/customs` placement: keep as Director queue (renamed) or merge FX actions into request detail (F-11 recommends detail panel; queue links there). Access backend-enforced via stage permissions, not frontend route-role maps.
**Acceptance:** UI says "External FX Confirmation"; old data intact.

---

## Part E — Financing advisory UI

### F-17 — Financing utilization bar rebuild (D22-N1)

**Current:** `useFinancingLedger` composable alive, zero UI consumers (bar deleted in cutover).
**Required:** rebuild indicator in the engine wizard near invoice/merchant/percentage fields: used %, remaining %, blocked/likely-to-exceed, low-capacity warning (≤20% remaining, `LOW_REMAINING_THRESHOLD`). Old `FinancingUtilizationBar.vue` (recoverable) is reference only. Advisory is informational; backend enforcement authoritative.
**Acceptance:** creator sees remaining capacity before submit; hard-failure-at-transition reduced.

---

## Business rules (consolidated)

1. Dynamic options are scope-aware; option catalogs are authoritative (membership enforced); deactivated values grandfathered.
2. FILE fields reference real server-validated documents; required FILE fields require evidence at stage exit.
3. Field visibility governs output (data + linked documents), not just input.
4. Documents are evidence: scanned, checksum-verified, replaceable-with-history, never silently removed.
5. FX confirmation is a complete flow: stage-permission-authorized, immutability-correct, terminology-clean, with restored UI.
6. Financing advisory gives creators pre-submit visibility; backend cap enforcement unchanged.

## Error cases

| Case | Response |
|------|----------|
| Unknown select value / bad date / non-boolean checkbox | 422 field error |
| Required FILE field without linked document at stage exit | 422 `STAGE_FIELDS_INVALID` |
| Infected/pending file download | 403/422 |
| Checksum mismatch on download | 403 + audit |
| FX upload by non-executor | 403 |
| Out-of-scope document/data access | empty / 403 |

## Acceptance criteria

1. Options scoped (F-1); membership enforced (F-2); grandfathering works (F-3); FILE references real docs (F-4); typed values validated (F-5).
2. Output + linked-document visibility respects field rules (F-6).
3. Required-FILE evidence (F-7); scanning (F-8, or schema-ready deferred); checksums (F-9); replacement-with-history (F-10).
4. FX panel restored (F-11); stage-permission auth (F-12); signed-doc replacement auditable (F-13); semantics split (F-14); immutability fixed (F-15); terminology + placement (F-16).
5. Financing bar restored (F-17).
6. All WP-0 suites green; WP-7 scope respected; WP-4 semantic mappings used for FX/financing field reads.

## Test cases

- **Unit (resolver/validator):** scoped options; membership; grandfathering; FILE reference; typed values; field visibility filtering.
- **Feature (documents):** required-FILE evidence; scan status flow; checksum verification; replacement history.
- **Feature (FX):** panel flow; stage-permission auth matrix; signed-doc replacement audit; immutability whitelist; terminology.
- **Feature (financing):** advisory bar values; low-capacity warning.
- **Regression:** existing document upload/download/delete (minus the new guards) unchanged for legitimate holders.

## Manual verification steps

1. Bank user fills a MERCHANTS field → own-bank only; submit unknown select value → rejected.
2. Required FILE field, no document → transition blocked; upload → passes.
3. Viewer without field visibility → no value, no linked document.
4. Replace a document → old superseded, history intact.
5. Director uploads signed FX → bank downloads deliverable; non-executor → 403.
6. Creator sees financing bar; low capacity → warning.

## Rollback considerations

Pieces are independent. F-8 (scanning) may ship schema-ready only if infra unavailable. F-10/F-13/F-14/F-15 schema additions are additive. F-11/F-17 are net-new frontend (restore points exist in git). FX terminology rename is UI-only (data intact).

## Open questions

1. **F-8 infra readiness:** is malware scanning deployable in this environment now, or ship schema-ready + defer enforcement? Needs infra input.
2. **F-16 `/customs` fate:** keep as renamed Director queue, or retire in favor of the detail-panel flow? Recommend detail-panel + retire queue (less surface).
3. **F-14 `issued_by` source:** who is the official business issuer if not the transition actor? Needs business confirmation (Director? a configured role?).
