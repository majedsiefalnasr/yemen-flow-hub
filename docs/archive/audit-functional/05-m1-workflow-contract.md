# M1 — Canonical V1 Workflow Contract (Designer-First, Approved)

**Status:** Approved subject only to (a) confirmation of the exact SWIFT document
fields and (b) validation of semantic-role values the current engine supports.
No code changed. Evidence date: 2026-07-11.

**Authority model:** The metadata-driven Workflow Designer + Workflow Engine are
the source of truth. `docs/user-view/` is **deprecated historical material** and
is not an authoritative workflow contract. `dynamic-workflow-engine/` (Lovable)
is a **reference implementation only**. Executive Voting is **out of V1 scope**
and must not be reintroduced.

This document supersedes the earlier voting-based M1 conclusion, which was
withdrawn because it was reconstructed from deprecated `docs/user-view/`.

---

## 1. What the evidence established (designer → runtime → seed → frontend)

| Check                                   | Result                                                                                                                                                                                                                               |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Designer metadata vs live DB            | **Agree.** Live `stage_permissions` carry proper org/team/role bindings (e.g. `CREATE` org=1/team=1; `EXEC` org=2/team=6/role=6). The earlier "all-NULL wildcard" alarm was a tinker relation-loading artifact and is **withdrawn**. |
| Runtime vs designer transition graph    | **Faithful, zero divergence.** Transitions gate on current stage + EXECUTE resolution + claim + field rules exactly as configured.                                                                                                   |
| Voting subsystem                        | **Dead.** `request_votes` table does not exist in the live DB; zero runtime path. Enums/exceptions/DTOs/resources are orphaned code. Lovable reference has no voting UI/logic either. → **Legacy/unused.**                           |
| Field-rule enforcement                  | Correct. `StageFieldRuleValidator` enforces required/hidden/read-only and required-FILE-needs-document exactly per `stage_field_rules`.                                                                                              |
| WF-003 root cause                       | At `FX`, the seed marks every field `is_required=false`, so the engine correctly requires nothing. **WF-003 is a designer-config gap, not an engine bug.**                                                                           |
| `semantic_role`                         | **NULL on every live stage** (0 stages populated). Designer-driven bucketing is inert; read-model/dashboard bucketing falls back to hard-coded literal stage codes.                                                                  |
| Hard-coded stage codes outside designer | Present in `EngineRequestReadModel`, `SemanticRegistry`, `DashboardStatsService` (literal `'EXEC'`, `'SUPPORT'`, `'FX_CONFIRM'`, `'CREATE'`).                                                                                        |

---

## 2. Approved V1 stage ownership (no voting)

Two-step decision model, no voting:
`Support approval → Executive Committee decision (EXEC) → SWIFT handling (FX) →
FX confirmation (FX_CONFIRM) → Director final confirmation (FINAL) → Completed`.

| Stage              | Semantic purpose                 | EXECUTE owner                                                       | VIEW                            | Claim   |
| ------------------ | -------------------------------- | ------------------------------------------------------------------- | ------------------------------- | ------- |
| `CREATE` (initial) | Request creation                 | `commercial_banks/entry` (Data Entry)                               | own-bank reviewer/admin         | no      |
| `INTERNAL`         | Bank internal review             | `commercial_banks/internal_review` (Bank Reviewer, not the creator) | own bank                        | no      |
| `SUPPORT`          | Support Committee review         | `national_committee/support`                                        | Bank Reviewer (tracking)        | **yes** |
| `EXEC`             | **Executive Committee decision** | `national_committee/executive` + role **`committee_manager`**       | Executive + Director cross-bank | no      |
| `FX`               | SWIFT document handling          | `commercial_banks/fx_ops` (SWIFT Officer)                           | own bank                        | no      |
| `FX_CONFIRM`       | FX confirmation                  | `national_committee/fx_confirmation`                                | banks VIEW own                  | no      |
| `FINAL`            | **Director final confirmation**  | `national_committee/executive` + role **`committee_director`**      | Executive + Director            | no      |
| `CLOSED_COMPLETED` | Terminal (COMPLETED)             | —                                                                   | governance VIEW                 | no      |
| `CLOSED_REJECTED`  | Terminal (REJECTED)              | —                                                                   | governance VIEW                 | no      |

**The only ownership change from the current seed:** `FINAL` EXECUTE moves from
role `committee_manager` to role `committee_director`. `EXEC` keeps
`committee_manager`. Segregation rule: the Executive Committee must not perform
the Director's final action, and the Director must not replace the earlier
Executive Committee review.

## 3. EXEC vs FINAL are distinct (not redundant)

|                   | `EXEC`                                                                                      | `FINAL`                                                                                         |
| ----------------- | ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Actor             | Executive Committee (`committee_manager`)                                                   | Committee Director (`committee_director`)                                                       |
| Question decided  | Approve the request to continue into the FX/SWIFT processing path, **or terminally reject** | Confirm completion **after** SWIFT + FX-confirmation requirements are satisfied                 |
| Allowed outgoing  | `APPROVE → FX`, `REJECT_FINAL → CLOSED_REJECTED`                                            | `FINAL_APPROVE → CLOSED_COMPLETED`, `REJECT → FX_CONFIRM` (rework)                              |
| Required evidence | Reasoned decision (comment required on reject)                                              | Reasoned decision (comment required on reject); relies on the SWIFT package already gated at FX |
| Outcome           | Proceed vs terminal rejection                                                               | Completion vs return-to-FX-confirmation                                                         |

These purposes must be documented in the designer UI so the two stages cannot be
confused.

## 4. Return / reject / cancel / abandon / complete (engine terms)

- **Return** — backward transition to an editable stage (e.g. `INTERNAL → CREATE`). Status stays ACTIVE.
- **Reject** — transition to an `is_final` stage with `final_outcome = REJECTED` (`CLOSED_REJECTED`) → engine status REJECTED. Note `FX_CONFIRM → FX` and `FINAL → FX_CONFIRM` are **rework** edges currently labeled REJECT, not terminal.
- **Cancel** (`CANCELLED`) — see §7: reserved; no current entry transition.
- **Abandon** (`ABANDONED`) — see §7: reserved; no current entry transition.
- **Complete** — transition to `is_final` + `final_outcome = COMPLETED` (`CLOSED_COMPLETED`) → status CLOSED.

## 5. SWIFT document requirements (proposed — WF-003 stays OPEN pending approval)

**Evidence source (not guessed):** the intended package already exists as orphaned
code — `backend/app/Http/Requests/UploadSwiftRequest.php` and
`frontend/app/components/workflow/SwiftUploadForm.vue`. Both define a three-part
package (the backend also keeps a legacy single-file mode).

**APPROVED contract (all three mandatory to leave FX).** Canonical keys verified
from source — reuse, do not duplicate:

- Backend API keys (`UploadSwiftRequest.php`): `swift_reference`, `swift_file`, `fx_request_file` (snake_case) + a legacy `file` single-upload mode.
- Frontend emit keys (`SwiftUploadForm.vue`): `swiftReference`, `swiftFile`, `fxRequestFile` (camelCase).
- The seeder currently has **no SWIFT field definitions** — B3 must add them using the canonical snake_case keys; the frontend camelCase must map to them (naming-consistency task).

| Field key (canonical) | Label (AR)                                  | Label (EN)                       | Type | File types                                     | Max size | Visible @FX | Editable @FX        | Required to leave FX | Visible @FX_CONFIRM / FINAL / terminal |
| --------------------- | ------------------------------------------- | -------------------------------- | ---- | ---------------------------------------------- | -------- | ----------- | ------------------- | -------------------- | -------------------------------------- |
| `swift_reference`     | رقم مرجع السويفت (UETR / Message Reference) | SWIFT reference                  | TEXT | —                                              | —        | yes         | yes (SWIFT Officer) | **yes**              | yes (read-only)                        |
| `swift_file`          | وثيقة السويفت (MT103 / MT202)               | SWIFT document                   | FILE | PDF only (MIME `application/pdf` + `.pdf` ext) | 10 MB    | yes         | yes (SWIFT Officer) | **yes**              | yes (read-only)                        |
| `fx_request_file`     | طلب تأكيد المصارفة الخارجية                 | External FX confirmation request | FILE | PDF only (MIME `application/pdf` + `.pdf` ext) | 10 MB    | yes         | yes (SWIFT Officer) | **yes**              | yes (read-only)                        |

- **All three mandatory.** The transition out of FX is blocked unless `swift_reference` is non-empty and both PDFs are present and valid. `swift_reference` format: validate against the known SWIFT-reference rule `^[A-Za-z0-9\-_/]{8,64}$` (present in `SwiftUploadForm.vue`) as a soft/known-format check; do not hard-reject on unusual-but-plausible references unless product provides a strict rule.
- **`fx_request_file` = mandatory — no contradiction found.** Source evidence: `SwiftUploadForm.vue` requires all three; `UploadSwiftRequest.php` co-requires them in package mode. No source proves it is optional-supporting, so per the M1 confirmation it is treated as a mandatory part of the approved package.
- **Legacy single-`file` mode** (`UploadSwiftRequest.php`) and the existing `EngineSwiftUploadTest` (which posts `['file' => …]`) must be migrated to the three-part package in B3.
- **Replacement / history:** re-uploading a SWIFT file supersedes the prior document (existing `superseded_by` mechanism on `engine_request_documents`); prior files remain in document history (not hard-deleted). PDF-only MIME + extension + SHA-256 checksum + malware-scan gates apply (existing document pipeline).
- **Download authorization at downstream stages:** SWIFT documents remain View+Download for own-bank SWIFT Officer, Bank Reviewer (bank record-keeper), and CBY governance per the existing document policy; hidden-field suppression does not apply because these fields are visible at FX_CONFIRM/FINAL.
- **Enforcement:** set the three fields `is_required = true` in `stage_field_rules` at `FX`. The engine's `StageFieldRuleValidator` (required-FILE-needs-linked-document) then blocks the empty-payload transition automatically — no hard-coding.
- **WF-003 status:** contract APPROVED; remains OPEN in the roadmap until B3 implements + tests it (route wiring, package validation, downstream visibility, legacy-test migration).

## 6. Canonical semantic-role mapping (APPROVED)

The live `App\Enums\StageSemanticRole` currently backs:
`INITIAL_ENTRY, BANK_REVIEW, SUPPORT_REVIEW, SWIFT, EXECUTIVE_VOTE,
FINANCE_RESERVE, FX_CONFIRMATION, FINAL`.

**Approved changes (M1 confirmation 2):**

- **Add a new canonical case `EXECUTIVE_REVIEW`.** Do **not** reuse `EXECUTIVE_VOTE` — voting is out of V1 and that name preserves incorrect business meaning.
- **Terminal stages get semantic roles** so dashboards/reporting/API/frontend have a stable meaning for completed/rejected without literal stage codes.

**Approved stage → semantic-role map:**

| Stage            | Semantic role (approved)    | Enum action                            |
| ---------------- | --------------------------- | -------------------------------------- |
| CREATE           | REQUEST_CREATION            | rename/add case (was `INITIAL_ENTRY`)  |
| INTERNAL         | BANK_INTERNAL_REVIEW        | rename/add case (was `BANK_REVIEW`)    |
| SUPPORT          | SUPPORT_COMMITTEE_REVIEW    | rename/add case (was `SUPPORT_REVIEW`) |
| EXEC             | EXECUTIVE_REVIEW            | **add new case**                       |
| FX               | SWIFT_DOCUMENT_HANDLING     | rename/add case (was `SWIFT`)          |
| FX_CONFIRM       | FX_CONFIRMATION             | keep `FX_CONFIRMATION`                 |
| FINAL            | DIRECTOR_FINAL_CONFIRMATION | rename/add case (was `FINAL`)          |
| CLOSED_COMPLETED | COMPLETED                   | terminal semantic                      |
| CLOSED_REJECTED  | REJECTED                    | terminal semantic                      |

**Enum-typing note (implementation decision for B4):** the approved names are the
canonical business meanings. The current enum uses shorter values. B4 must adopt
these canonical values in the enum. If the engine benefits from distinguishing
**active-stage semantics** from **terminal-outcome semantics**, use an
appropriately typed terminal semantic field for `COMPLETED`/`REJECTED` rather than
forcing incompatible enum values into the active-stage enum — but the API must
still expose a stable semantic meaning for the two terminal stages either way.
`FINANCE_RESERVE` maps to no current stage — record as unused/legacy.

Queues/dashboards/read-models should primarily use `semantic_role`; literal
stage-code fallbacks may remain temporarily for V1 backward compatibility and are
**recorded for later removal** once every active workflow version has semantic
metadata.

## 7. CANCELLED and ABANDONED — reserved (documented completeness gap)

Both remain supported runtime statuses. **No new designer transition is added in
this correction** (no existing approved business rule was found that produces
them).

- `CANCELLED` — reserved explicit cancellation outcome. Future decision required: actor, stage eligibility, reason capture, reversibility.
- `ABANDONED` — reserved system/administrative outcome. Future decision required: inactivity/lifecycle policy.
- Neither is silently mapped to REJECTED.

This is recorded as a **workflow-completeness decision**, not an automatic defect.

## 8. Legacy classification (approved; no deletion during audit)

| Item                                                                                                         | Classification                                                        | Action                                                                                                 |
| ------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `docs/user-view/`                                                                                            | Deprecated historical material                                        | Mark deprecated now (this report); archive/delete only in a separately approved cleanup task           |
| Voting stack (enums, exceptions, DTOs, resources, `eligible_voter_ids`, `StageSemanticRole::EXECUTIVE_VOTE`) | Legacy/orphaned — confirmed no `request_votes` table, no runtime path | Pending confirmation of no route/queue-job/active-test dependency, then remove in a later cleanup task |
| `dynamic-workflow-engine/`                                                                                   | Reference implementation                                              | Keep unless a later repo-cleanup decision says otherwise                                               |
| Hard-coded literal stage-code bucketing                                                                      | Temporary V1 compatibility                                            | Record for removal once semantic metadata is populated on all active versions                          |

## 9. The 48 active V1 requests — classification report

Corrected V2 publishing is approved; auto-finishing all 48 on defective V1 is
**not** approved. Classification (from live DB):

| Signal                    | Value                                                 | Interpretation                                   |
| ------------------------- | ----------------------------------------------------- | ------------------------------------------------ |
| `created_at`              | all 48 at `2026-05-01 09:00:00` (identical)           | Machine-seeded, not organic                      |
| Reference pattern         | `ENG-2026-YBRD-A0xx` (28) + `ENG-2026-TIIB-A0xx` (28) | Deterministic seeder output                      |
| Distinct banks / creators | 2 / 2                                                 | Synthetic fixture spread                         |
| Linked documents          | 50                                                    | Non-trivial footprint — reset must be deliberate |
| `workflow_history` rows   | 196                                                   | Rich history exists on these records             |
| Source                    | `EngineRequest*Seeder` catalog (synthetic)            | No confirmed organic UAT data                    |

Current-stage distribution of the 48 ACTIVE:

| Stage      | Count | Exposure to Critical defects                                                            |
| ---------- | ----- | --------------------------------------------------------------------------------------- |
| INTERNAL   | 12    | —                                                                                       |
| SUPPORT    | 8     | —                                                                                       |
| EXEC       | 6     | WF-002 (Executive decides directly — approved as V1 behavior, so not a defect after M1) |
| FX         | 6     | **WF-003** (can leave without SWIFT package)                                            |
| FX_CONFIRM | 6     | downstream of FX gap                                                                    |
| FINAL      | 6     | **WF-002** ownership (currently `committee_manager`, should be `committee_director`)    |
| CREATE     | 4     | —                                                                                       |

Terminal already on V1: 4 CLOSED + 4 REJECTED (preserve for history).

**Classification verdict:** all 48 are **demo/synthetic seed data**, not UAT
business data. **Recommended default:** reset/recreate the 48 under V2 rather than
migrate; preserve the 8 terminal V1 records for history if useful. No active
request should continue on V1 while exposed to a confirmed Critical defect
(the 12 at FX/FINAL). Any migration path (if UAT value is later asserted for
specific records) requires a dry run, explicit stage mapping, history+audit
preservation, rollback instructions, and separate approval.

## 10. Remaining M1 sign-off items

1. **SWIFT fields (§5):** confirm the three field keys/labels and whether `fxRequestDocument` is mandatory to leave FX. WF-003 stays open until approved + tested.
2. **Semantic `EXEC` value (§6):** approve adding `EXECUTIVE_REVIEW` enum case (recommended) vs reusing `EXECUTIVE_VOTE` vs NULL-with-fallback; and whether terminal stages need semantic cases.

Everything else in this document is locked per the M1 approval.
