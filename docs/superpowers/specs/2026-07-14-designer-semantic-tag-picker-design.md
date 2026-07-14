# Designer Semantic-Tag Picker — Design

Date: 2026-07-14
Status: Approved (pending spec review)

## Problem

`FieldSemanticTag` (`INVOICE_NUMBER`, `MERCHANT_TAX_NUMBER`, the six merchant-autofill tags added for the tax-number-search feature, etc.) drives real runtime behavior — the financing ledger advisory bar, merchant tax-number autofill, and FX-confirmation PDF field resolution all key off `FieldDefinition.semantic_tag`. But nothing in the Workflow Field Designer UI lets an admin set it. Today the only way to assign a tag to a field is a direct database write (`php artisan tinker`), which is how the merchant-autofill feature had to be verified and enabled on the one live workflow version that uses it.

Backend is already fully ready for this: `StoreFieldDefinitionRequest` and `UpdateFieldDefinitionRequest` both accept and validate `semantic_tag` (`Rule::enum(FieldSemanticTag::class)`, nullable), and `SemanticResolver::publishErrors()` already blocks publishing a workflow version where two fields share the same tag (`SEMANTIC_MAPPING_AMBIGUOUS`). The gap is entirely in `WorkflowFieldDesigner.vue`, which has no form control for this column and never includes it in the create/update payload.

## Decisions

### 1. Frontend-only change, no backend work

Verified `StoreFieldDefinitionRequest.php:35` and `UpdateFieldDefinitionRequest.php:26` both already validate `semantic_tag`, and `WorkflowDesignerService::cloneVersion` already carries `semantic_tag` forward when a version is duplicated. No new endpoint, no new validation rule, no migration. This is a UI-only addition on top of an already-correct backend contract.

### 2. Tag catalog lives in one new frontend file

New `frontend/app/constants/semanticTags.ts` exports `SEMANTIC_TAG_GROUPS`: an ordered array of `{ label: string; tags: Array<{ value: FieldSemanticTag; label: string }> }`. Three groups, matching the tags' actual current usage:

- **التاجر** (merchant): `MERCHANT_TAX_NUMBER`, `MERCHANT_ID`, `MERCHANT_COMPANY_ID`, `MERCHANT_TAX_CARD_EXPIRY`, `MERCHANT_COMMERCIAL_REGISTRATION_NUMBER`, `MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY`, `MERCHANT_OWNERS`
- **التمويل** (financing/FX): `INVOICE_NUMBER`, `REQUESTED_PERCENTAGE`, `AMOUNT`, `CURRENCY`
- **أخرى** (other): `SUPPLIER_NAME`, `GOODS_DESCRIPTION`, `PORT_OF_ENTRY`

Both the field dialog's picker and the fields-table badge tooltip import their label text from this one file — a 15th tag only needs one new entry here.

No type-based filtering: the picker offers all 14 tags for any field type, matching the fact that neither the DB schema nor backend validation constrains `semantic_tag` by `FieldType`. Inventing a compatibility matrix that doesn't exist anywhere else in the codebase is out of scope.

### 3. Client-side duplicate-tag warning, not a new endpoint

`WorkflowFieldDesigner.vue` already loads all groups/fields for the current version via `useWorkflowFields()` before the dialog ever opens. A `computed` (`tagOwners: Map<FieldSemanticTag, FieldDefinition>`) built from the already-loaded `flatFields` is sufficient to know, at picker-render time, which tags are already claimed in this version — no new API call needed, since the data is already in memory.

In the picker, a tag already owned by a field *other than* the one being edited is disabled with an inline note ("مستخدم في: {field.label}"). This is UX-only, client-side, and can theoretically go stale if two admins edit the same workflow version concurrently — that race is already covered by the existing optimistic-locking `version` field on `updateField`, and by the backend's authoritative `SEMANTIC_MAPPING_AMBIGUOUS` publish-time check, which fires regardless of what the client-side picker allowed through. No new conflict-handling code needed.

### 4. Field dialog: one new grouped `Select`

`WorkflowFieldDesigner.vue` gets:

- `fieldSemanticTag = ref<FieldSemanticTag | ''>('')`, reset in `openFieldDialog` (to `''`) and populated in `openEditFieldDialog` (from `field.semantic_tag ?? ''`), same pattern as every other field-dialog ref.
- A new form block placed after the "النوع" (type) select, before the dynamic-source block: a `Select` with a "بدون" (none) top item, then one `SelectGroup`/`SelectLabel` per `SEMANTIC_TAG_GROUPS` entry, each `SelectItem` disabled + annotated when `tagOwners.get(tag) !== undefined && tagOwners.get(tag)?.id !== editingField?.id`.
- `submitField()`'s payload gains `semantic_tag: fieldSemanticTag.value || null`.

### 5. Fields table: tag badge

The existing fields table (`الرمز` / `الاسم` / `النوع` / `المجموعة` / `إجراء` columns) gets a small tag-icon `Badge` in the `الاسم` cell, shown only when `field.semantic_tag !== null`, wrapped in a `Tooltip` showing the tag's Arabic label from the catalog. This lets an admin audit which fields in a workflow are already wired to runtime behavior without opening each field's edit dialog — directly addresses why this gap mattered in the first place (the merchant-autofill feature was invisible in the Designer even after being tagged).

### 6. Types

`FieldDefinitionPayload` (`useWorkflowFields.ts`) gains `semantic_tag?: FieldSemanticTag | null`. `FieldDefinition` (`types/models.ts`) already has this field from the Bug 3 work — no change needed there.

### 7. Error handling

No new error paths. If the backend rejects a tag (e.g. a race against another admin's concurrent edit, or a future stricter validation rule), the existing `formError` display / `toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ الحقل'))` in `submitField()`'s catch block already surfaces whatever message the backend returns — no new error code or copy needed.

### 8. Testing

Extend `WorkflowFieldDesigner.test.ts`:

- Tag picker renders all three groups with correct Arabic labels.
- Submitting a field with a selected tag includes `semantic_tag` in the create/update payload.
- Opening the edit dialog for an already-tagged field pre-selects that tag in the picker.
- A tag already used by another field in the same version renders as disabled in the picker, with the owning field's label shown.
- The fields table shows the tag badge only for fields with a non-null `semantic_tag`, and omits it otherwise.
- Submitting with no tag selected sends `semantic_tag: null` (not `undefined`, not omitted) — matches the nullable-not-optional contract the backend validates against.

## Out of scope

- Any change to `SemanticResolver`, `SemanticRegistry`, or the legacy field-key alias fallback mechanism (`fieldKeyAliases()`) — those stay exactly as they are.
- Backfilling `semantic_tag` on any existing workflow version's fields. This spec only adds the UI; using it to tag the live IMPORT_FINANCING v2 fields (already done via `tinker` for the merchant-autofill feature) is a separate, already-completed data task, not part of this implementation.
- A tag→field-type compatibility matrix (see Decision 2).
- Server-side duplicate-tag pre-check endpoint (see Decision 3) — the existing publish-time check remains authoritative.
