# WP-8 Documents, Fields, and FX Completion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Complete dynamic field options/validation, output visibility, document integrity, FX confirmation flow, and financing advisory UI.

**Authority:** `docs/superpowers/specs/2026-07-06-wp8-documents-fields-and-fx-completion.md`

**Branch:** `feat/wp8-documents-fields-fx`

## Global Constraints

- Signed commits; `Co-Authored-By: Claude <noreply@anthropic.com>`
- Use `DataScope` (WP-7) for option scoping; `SemanticResolver` (WP-4) for FX/financing fields
- shadcn-vue mandatory on frontend; no raw HTML controls
- F-8 malware scan: schema-ready + defer enforcement if no infra (per open question)
- Focused tests per task

---

### Task 1: F-1 Bank-scoped merchant/company options

Modify `DynamicFieldOptionsResolver` to accept actor + request bank context; apply `DataScope` for MERCHANTS/MERCHANT_COMPANIES.
Tests: bank user sees own-bank only; NC on request sees request bank.
Commit: `feat(workflow): scope dynamic field options by DataScope (WP-8 F-1)`

### Task 2: F-2 SELECT membership validation

Add select-membership check in `StageFieldRuleValidator` for static + dynamic options.
Commit: `feat(workflow): validate select values against option sets (WP-8 F-2)`

### Task 3: F-3 Grandfathering deactivated options

Stored values remain readable; new selection requires active option.
Commit: `feat(workflow): grandfather deactivated dynamic option values (WP-8 F-3)`

### Task 4: F-4 FILE field document references

FILE values reference `EngineRequestDocument` id; reject client metadata-only.
Commit: `feat(workflow): bind FILE fields to server documents (WP-8 F-4)`

### Task 5: F-5 Typed value validation

DATE ISO-8601, CHECKBOX boolean, default_value per type.
Commit: `feat(workflow): validate typed field values at save and transition (WP-8 F-5)`

### Task 6: F-6 Output field visibility

Filter `EngineRequestResource` data + linked documents by viewer visibility rules.
Commit: `feat(workflow): enforce field visibility on output and documents (WP-8 F-6)`

### Task 7: F-7 Required FILE evidence at stage exit

Transition validation requires linked document for required FILE fields.
Commit: `feat(workflow): require FILE field evidence before stage exit (WP-8 F-7)`

### Task 8: F-8 Scan status schema (defer enforcement if needed)

Add `scan_status` on documents; block download unless clean when enforced.
Commit: `feat(workflow): add document scan status schema (WP-8 F-8)`

### Task 9: F-9 Checksum verification on download

Verify sha256 on download; block + audit on mismatch.
Commit: `feat(workflow): verify document checksum on download (WP-8 F-9)`

### Task 10: F-10 Document replacement flow

`superseded_by` FK; replacement audited; no silent delete.
Commit: `feat(workflow): add controlled document replacement flow (WP-8 F-10)`

### Task 11: F-11 FX panel rebuild

`EngineFxConfirmationPanel.vue` on instance detail page.
Commit: `feat(frontend): rebuild FX confirmation panel (WP-8 F-11)`

### Task 12: F-12 FX stage-permission auth

Remove role-code gates; EXECUTE/VIEW + DataScope.
Commit: `feat(workflow): authorize FX via stage permissions (WP-8 F-12)`

### Task 13: F-13–F-15 FX replacement, semantics, immutability

Signed-doc replacement audit; generated_by/signed_uploaded_by; whitelist mutable columns.
Commit: `feat(workflow): fix FX semantics and immutability model (WP-8 F-13–F-15)`

### Task 14: F-16 FX terminology + placement

Rename UI copy to External FX Confirmation; detail-panel primary.
Commit: `feat(frontend): rename FX confirmation terminology (WP-8 F-16)`

### Task 15: F-17 Financing utilization bar

Rebuild bar in wizard using `useFinancingLedger`.
Commit: `feat(frontend): restore financing utilization advisory bar (WP-8 F-17)`

### Task 16: WP-8 gate

Run focused WP-8 test suite + smoke; merge to main.
