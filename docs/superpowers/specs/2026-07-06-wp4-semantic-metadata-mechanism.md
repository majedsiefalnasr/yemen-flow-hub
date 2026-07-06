# WP-4 — Semantic Metadata Mechanism

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md` — Phase 4 SW-2 (highest-leverage architectural investment); D11-N4 (FX PDF mapping), D2-N3 (explicit effect attachment), D21-N1 (dashboard buckets), D22-N2 (financing mapping validation + no-silent-skip)
**Dependencies:** WP-3 (validator infra + the `is_default_submit`/`transition_type`/`final_outcome` building blocks already shipped there).
**Enables:** WP-8 (FX PDF rebuild, financing advisory, field validation), WP-7 dashboard buckets (replaces stage-code buckets), WP-12 (runtime read of the mechanism).
**Overall risk:** high — the architecture decision that frees the metadata-driven engine from hardcoded semantic anchors (stage codes, field keys). Mitigated by being one mechanism shared across four integrations, with publish validation closing the silent-skip class.

## Change classification

| Item | Kind |
|------|------|
| M-1 design study (mechanism selection) | Design decision (Phase 6 output) |
| M-2 `WorkflowSemantic` data model + resolver | Approved functional + migration |
| M-3 designer authoring surface | Approved functional |
| M-4 publish-time validation for semantic dependencies | Approved functional (D11-N4, D22-N2, D21-N1) |
| M-5 consumer migrations (FX/financing/dashboard/effects) | Approved functional, one per integration |

**Explicitly out of scope:** rebuilding the FX advisory UI / financing bar / field membership enforcement (those are WP-8/WP-22-UI); two-layer visibility (WP-7); the runtime submit-transition read (WP-12). This package builds the *mechanism and validation*; consumers adopt it but their full feature work lands in their owning packages.

---

## M-1 — Design study (mechanism selection)

D11-N4 lists five candidate approaches. This spec picks one and records the trade-off analysis as a decision artifact (`docs/decisions/semantic-mapping.md`, a short ADR). Required output: the chosen mechanism and why, plus the rejection rationale for the others.

**Recommended mechanism (subject to reviewer override): Option 1 — semantic field tags + explicit effect configuration, with a registry-driven fallback.**

- **Tags** on field definitions (`field_definitions.semantic_tag`, nullable enum): e.g. `INVOICE_NUMBER`, `REQUESTED_PERCENTAGE`, `MERCHANT_TAX_NUMBER`, `SUPPLIER_NAME`, `GOODS_DESCRIPTION`, `PORT_OF_ENTRY`, `AMOUNT`, `CURRENCY`. Multiple fields may share a tag is disallowed (one field per semantic tag per version — validated).
- **Stage semantic role** on stages (`workflow_stages.semantic_role`, nullable enum): e.g. `INITIAL_ENTRY`, `BANK_REVIEW`, `SUPPORT_REVIEW`, `SWIFT`, `EXECUTIVE_VOTE`, `FINANCE_RESERVE`, `FX_CONFIRMATION`, `FINAL`. Replaces dashboard stage-code buckets (D21-N1).
- **Effect attachment** via explicit stage metadata: `workflow_stages.attached_effects` JSON (list of registered effect codes, e.g. `['financing.reserve','fx.confirmation_pdf']`) replaces the config-stage-code binding (D2-N3). Stage-code renames no longer detach behavior.
- **Registry**: `App\Services\Workflow\SemanticRegistry` — declares each semantic tag/role/effect, its required mappings, and a safe fallback policy (block vs degrade). One place to extend.

**Why Option 1 over the others:**
- Option 2 (purpose/type mapping) and Option 4 (standardized aliases) collapse into tags — tags are the simpler shape.
- Option 3 (configurable PDF template mapping) is heavier and pushes designer burden; tags are lighter and validate earlier.
- Option 5 (metadata/label/alias fallback search) is kept as a *fallback resolver* inside the registry for legacy/migration, never the primary path — it's fragile by nature (D11-N4's own caveat).

**Reviewer call:** if you prefer Option 3's explicit template mapping for the PDF specifically (more control, more designer work), the mechanism can be tag-primary with an optional per-effect config override — flag your preference.

## M-2 — Data model + resolver

**Schema (additive):**
- `field_definitions.semantic_tag` (nullable enum string, indexed).
- `workflow_stages.semantic_role` (nullable enum string, indexed).
- `workflow_stages.attached_effects` (nullable JSON array).
- `workflow_semantic_dependencies` — optional denormalized table or computed at validate-time; MVP computes at validate-time from the registry + tags, no table.
- Clone support (extend `deepCopyVersionConfig` in `WorkflowDesignerService`) carries all three.

**Resolver:**
- `App\Services\Workflow\SemanticResolver` — given a (WorkflowVersion, semantic key), returns the resolved field/stage or `null`. Pure read; reused by FX effect, financing effect, dashboard bucket derivation, and the validator.
- **Resolution order:** explicit tag → registry alias fallback (legacy) → null. Null resolution with a required mapping ⇒ validation error (M-4).

## M-3 — Designer authoring surface

- **Stage editor:** `semantic_role` select (optional but required when an effect depending on a role is attached), `attached_effects` multi-select (limited to registered effects). Effect selection reveals the required-field checklist (M-4) inline.
- **Field editor:** `semantic_tag` select (optional; one field per tag per version).
- **Designer guidance:** when an effect is attached, the editor shows "required mappings: INVOICE_NUMBER ✓ / REQUESTED_PERCENTAGE ✗" so the designer fixes gaps before publish, not at validation failure.
- Permissions/authority: existing `workflow_designer` capability.

## M-4 — Publish-time validation (the silent-skip closer)

Extends `WorkflowVersionValidator` (WP-3 infra). Per the registry, for every **attached effect** and every **semantic role used by dashboards/reporting**, the validator asserts the required mappings resolve:

| Effect / consumer | Required semantic mappings (registry-declared) |
|-------------------|------------------------------------------------|
| `financing.reserve` | MERCHANT_TAX_NUMBER, INVOICE_NUMBER, REQUESTED_PERCENTAGE |
| `fx.confirmation_pdf` | at minimum AMOUNT, CURRENCY, INVOICE_NUMBER/MERCHANT_TAX_NUMBER; plus SUPPLIER_NAME, GOODS_DESCRIPTION, PORT_OF_ENTRY if the template renders them |
| Dashboard/reporting roles | semantic_role on at least one stage per expected role; else a warning (dashboards degrade, not block) |

**Error codes:** `SEMANTIC_MAPPING_MISSING` (blocking, regulatory effects), `SEMANTIC_MAPPING_AMBIGUOUS` (multiple fields share a required tag — disallowed). Dashboard-role gaps → warning list, not blocking (D21-N1 says warn-or-block per importance; dashboards degrade).

**No-silent-skip runtime policy (D22-N2):**
- For **enforcing** effects (financing, FX for regulatory workflows): a missing required mapping at runtime → effect **fails the transition** (rolls back) instead of skipping. Defense-in-depth behind the publish rule.
- For explicitly **non-enforcing** effects: skip allowed but logs a high-severity configuration warning + audit entry.
- The registry declares each effect's policy (`FAIL` vs `WARN`).

## M-5 — Consumer migrations (one per integration)

Each consumer migrates from its hardcoded anchor to the resolver. These are tagged commits, one per integration, behind the mechanism:

1. **Financing effect** (`FinancingLedgerEffect`) — reads merchant tax / invoice / percentage via `SemanticResolver` instead of hardcoded JSON keys + relation; missing mapping → fail the transition (enforcing). The advisory UI rebuild is WP-8 (D22-N1).
2. **FX PDF effect** (`CustomsFxPdfEffect` + `CustomsDeclarationGenerator` snapshot) — resolves PDF fields via tags; `CustomsFxPdfEffect::snapshot` reads tagged fields; approval dates derived from `workflow_history` (D11-N4) instead of hardcoded null. FX UI rebuild is WP-8 (D11-N1).
3. **Dashboard buckets** (`EngineRequestReadModel`) — stage buckets keyed on `semantic_role` instead of stage code; D21-N1 realization. T-4 snapshots from WP-0 become the migration oracle (existing fixtures map cleanly when stages carry the migrated role).
4. **Effect binding** (`StageHookRegistry` + `AppServiceProvider`) — hooks fire on stages carrying the `attached_effects` entry, not on global config stage code (D2-N3). Config keys become migration hints / bootstrap only.

Each consumer's migration includes a **backfill** of the semantic fields for existing PUBLISHED versions (reviewed mapping, logged, like WP-1/WP-2 migrations) so runtime reads resolve without designer action — published-version immutability governs *designer* edits, not a sanctioned data migration.

---

## Business rules (consolidated)

1. One semantic mechanism (tags + roles + attached effects + registry) for all workflow integrations; no new integration may hardcode field keys or stage codes.
2. Every attached enforcing effect must resolve all required mappings at publish; runtime fails safely rather than silently skipping.
3. Stage-code/field-key renames no longer affect integrations; the registry is the source of truth.
4. Dashboard/reporting semantic roles are best-effort (warn on gap); regulatory effects are strict (block).
5. Existing PUBLISHED versions backfilled; DRAFT versions must satisfy validation at publish.

## Error cases

| Case | Layer | Code |
|------|-------|------|
| Required semantic mapping missing at publish | publish 422 | `SEMANTIC_MAPPING_MISSING` |
| Multiple fields share a required tag | publish 422 | `SEMANTIC_MAPPING_AMBIGUOUS` |
| Required mapping missing at runtime (enforcing effect) | transition rollback | propagated domain exception (e.g. financing `FINANCING_MAPPING_UNRESOLVED`) |
| Dashboard role gap | publish warning | `SEMANTIC_DASHBOARD_ROLE_GAP` (non-blocking) |

## Acceptance criteria

1. ADR exists; mechanism chosen; registry covers the three current integrations + dashboard roles.
2. Designer attaches an effect → required-mapping checklist shown; gaps fixed before publish; publish blocked on unresolved enforcing mappings.
3. Renaming a stage code or field key in a published-and-tagged version changes **nothing** about financing/FX/dashboard behavior (the headline safety property — explicit test).
4. Runtime enforcing effect with missing mapping rolls the transition back (not silent skip).
5. Dashboard buckets resolve via `semantic_role`; T-4 snapshots preserved after backfill.
6. All WP-0 suites green; WP-3 rules not duplicated.

## Test cases

- **Unit (resolver + registry):** resolution order; alias fallback; ambiguity; missing-required.
- **Unit (validator):** each enforcing effect's required-mapping set blocks/approves; dashboard-role warning path.
- **Feature (consumer migration):** financing effect reads tagged fields; FX snapshot uses tagged fields + history-derived dates; dashboard buckets by role; effect binding by `attached_effects`.
- **Property test (headline):** rename stage code / field key on a tagged version → financing/FX/dashboard outputs unchanged.
- **Migration test:** backfill assigns expected tags/roles to seeded versions.

## Manual verification steps

1. Designer: attach financing effect → editor shows the three required mappings; leave one unset → publish blocked with the named error; set it → publishes.
2. Rename a stage code on a tagged published version → trigger a financing-reserve transition → cap still enforced (unchanged).
3. FX stage: render the declaration → fields populated from tagged fields, approval dates from history.
4. Dashboard: roles map to buckets; an untagged role degrades with a designer warning.

## Rollback considerations

Mechanism is additive (three nullable columns + registry class). Consumer migrations are independently revertible **but** once a PUBLISHED version is backfilled with tags and a consumer reads them, reverting the consumer without un-backfilling is safe (consumers fall back to current hardcoded behavior when tags are present-but-unused only if a compatibility shim is kept — recommend keeping the shim for one release). Full rollback = revert consumers + drop columns; data migrations are non-destructive (additive tags).

## Open questions

1. **M-1 mechanism confirmation:** approve Option 1 (tags + roles + attached effects + registry, alias fallback for legacy), or prefer Option 3 (explicit per-effect config mapping) as primary? This is the one genuine design decision in the package.
2. **Approval-date source for FX PDF:** confirm `workflow_history` derivation (first entry into BANK_REVIEW / SUPPORT / EXECUTIVE stages by `semantic_role`) is acceptable — needs the role backfill to be correct.
3. **Effect registry extensibility:** confirm the registry is code-only (new effect = code change + registry entry) vs designer-extensible — recommend code-only for now (YAGNI; designer-extensible effects are a large surface).
