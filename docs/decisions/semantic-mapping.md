# ADR: Workflow Semantic Mapping

**Status:** Accepted (WP-4, 2026-07-06)

## Context

Financing capacity, FX PDF generation, and dashboard buckets were keyed on hardcoded stage codes and field keys. Renaming a stage or field in the designer could silently detach integrations.

## Decision

Adopt **Option 1**: explicit semantic tags on fields, semantic roles on stages, and `attached_effects` on stages, backed by a code-only `SemanticRegistry` and `SemanticResolver`.

Resolution order:

1. Explicit `semantic_tag` / `semantic_role` on the version
2. Registry alias fallback (legacy field keys and stage codes)
3. `null` (blocking for enforcing effects at publish/runtime)

## Rejected alternatives

- **Purpose/type mapping** — redundant with tags
- **Configurable PDF template mapping** — heavier designer burden; deferred
- **Metadata/label search fallback** — kept only as alias fallback, never primary

## Consequences

- Publish blocks when enforcing effects lack required tags (`SEMANTIC_MAPPING_MISSING`)
- Dashboard role gaps warn only (`SEMANTIC_DASHBOARD_ROLE_GAP`)
- `StageHookRegistry` fires effect handlers from `attached_effects`; config stage codes remain a bootstrap fallback
- IMPORT_FINANCING published versions are backfilled via migration
