# Story 4.4: Document Checklist Component (Frontend)

## Status: review

## Story

**As** any authenticated user,
**I want** the Documents tab to show a clear checklist of required and uploaded documents with role-appropriate actions,
**So that** I can verify document completeness and download what I'm permitted to access.

## Acceptance Criteria

**AC1 — Document list with all metadata**
**Given** I open the "Documents" tab on `/requests/{id}`
**When** the tab renders
**Then** a document list shows all `request_documents` records: document type label, original filename, file size, uploaded by name, upload date

**AC2 — Role-gated Download button per document type**
**And** each document has a "Download" button — visible only if my role permits per the permission matrix:
- `REQUEST_DOC` type → all roles see the button (backend enforces bank scope)
- `SWIFT` type → only BANK_REVIEWER, SWIFT_OFFICER, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN see the button; DATA_ENTRY and SUPPORT_COMMITTEE do not
- Customs declaration → only BANK_REVIEWER, COMMITTEE_DIRECTOR, CBY_ADMIN see the download button

**AC3 — Upload Document button for DATA_ENTRY on editable requests**
**And** for DRAFT / DRAFT_REJECTED_INTERNAL requests: an "Upload Document" button is shown for DATA_ENTRY users
**And** clicking the button triggers a PDF-only file picker
**And** after a successful upload the document list refreshes automatically

**AC4 — Locked note for non-editable requests**
**And** for locked requests (any status other than DRAFT / DRAFT_REJECTED_INTERNAL): the upload button is hidden and a "مقفل — لا يمكن تعديل المستندات" note is shown for DATA_ENTRY users
**And** non-DATA_ENTRY roles never see an upload section

**AC5 — SWIFT badge**
**And** SWIFT documents are clearly labelled with a "SWIFT" badge styled in SWIFT Cyan (`#32ade6`)

**AC6 — Customs declaration in the document list**
**And** if a customs declaration PDF has been issued (request has `customs_declaration` populated), it appears as a row in the document list with a "بيان جمركي" label
**And** authorized roles (BANK_REVIEWER, COMMITTEE_DIRECTOR, CBY_ADMIN) see a download button for it; all other roles do not

## Tasks / Subtasks

- [x] Task 1: Create `useDocumentPermissions.ts` composable with pure permission functions
  - [x] 1.1 Create `frontend/app/composables/useDocumentPermissions.ts`
  - [x] 1.2 Export `canDownloadDocument(role: UserRole, docType: string | null): boolean` — true for REQUEST_DOC for all roles; true for SWIFT only for BANK_REVIEWER, SWIFT_OFFICER, EXECUTIVE_MEMBER, COMMITTEE_DIRECTOR, CBY_ADMIN
  - [x] 1.3 Export `canDownloadCustoms(role: UserRole): boolean` — true only for BANK_REVIEWER, COMMITTEE_DIRECTOR, CBY_ADMIN
  - [x] 1.4 Export `canUploadDocument(role: UserRole, status: RequestStatus): boolean` — true only for DATA_ENTRY + (DRAFT | DRAFT_REJECTED_INTERNAL)
  - [x] 1.5 Export `isDocumentModificationLocked(status: RequestStatus): boolean` — true for any status other than DRAFT and DRAFT_REJECTED_INTERNAL

- [x] Task 2: Create `DocumentChecklist.vue` presentation component
  - [x] 2.1 Create `frontend/app/components/requests/DocumentChecklist.vue`
  - [x] 2.2 Accept props: `documents: RequestDocument[]`, `customsDeclaration: CustomsDeclarationSummary | null`, `userRole: UserRole`, `requestStatus: RequestStatus`, `loading: boolean`, `error: string | null`, `uploadingDocument: boolean`, `uploadError: string | null`, `downloadingIds: Set<number>`, `downloadErrors: Record<number, string>`, `customsDownloading: boolean`, `customsDownloadError: string | null`
  - [x] 2.3 Emit events: `download(docId: number, filename: string)`, `download-customs(customsId: number, filename: string)`, `upload(file: File)`
  - [x] 2.4 Render skeleton/loading state, error state, and empty state
  - [x] 2.5 Render document list: show type label ("مستند طلب" / "مستند SWIFT"), SWIFT badge for SWIFT type, original filename, formatted file size, uploader name, formatted upload date
  - [x] 2.6 Show Download button per document — visible if `canDownloadDocument(userRole, doc.type)` is true; button shows spinner while downloading
  - [x] 2.7 Render customs declaration row if `customsDeclaration` is non-null: show "بيان جمركي" label, declaration number as filename, issued date; show download button only if `canDownloadCustoms(userRole)` is true
  - [x] 2.8 Render upload section at the bottom of the card: if `canUploadDocument(userRole, requestStatus)` → show hidden `<input type="file" accept="application/pdf">` + "رفع مستند" button that triggers it; if DATA_ENTRY + `isDocumentModificationLocked(requestStatus)` → show locked note; non-DATA_ENTRY → render nothing in upload section
  - [x] 2.9 Apply RTL layout (`dir="rtl"`), SWIFT badge using `#32ade6`, card styles consistent with existing page cards

- [x] Task 3: Add `uploadDocument` action to `requests.store.ts`
  - [x] 3.1 Add `uploading: false` and `uploadError: null as string | null` to store state
  - [x] 3.2 Add `uploadDocument(id: number, file: File): Promise<void>` action — calls `useRequests().uploadDocument(id, file, file.name)`, then calls `this.loadDocuments(id)` to refresh
  - [x] 3.3 Reset `uploading` and `uploadError` in the `$reset` / initial-state return

- [x] Task 4: Wire `DocumentChecklist` into the Documents tab of `requests/[id]/index.vue`
  - [x] 4.1 Import `DocumentChecklist` and `useDocumentPermissions` in `requests/[id]/index.vue`
  - [x] 4.2 Replace the inline document list markup in the Documents tab section with `<DocumentChecklist />`; pass all required props from page state and store
  - [x] 4.3 Handle `@upload` event: call `requestsStore.uploadDocument(id, file)`
  - [x] 4.4 Handle `@download` event: delegate to existing `downloadDocument(docId, filename)` function
  - [x] 4.5 Handle `@download-customs` event: delegate to existing `downloadCustomsDeclaration()` function
  - [x] 4.6 Pass `uploadingDocument` from `requestsStore.uploading` and `uploadError` from `requestsStore.uploadError`

- [x] Task 5: Write tests
  - [x] 5.1 Create `frontend/app/tests/unit/composables/useDocumentPermissions.test.ts` — test all permission functions exhaustively across all 7 roles and relevant document types / statuses
  - [x] 5.2 Create `frontend/app/tests/unit/components/DocumentChecklist.test.ts` — pure-logic extraction tests: permission gating logic (show/hide download button per role+type), locked note vs upload button logic, SWIFT badge condition, customs row condition
  - [x] 5.3 Create `frontend/app/tests/unit/stores/requests.store.upload.test.ts` — test `uploadDocument` action: success path (calls uploadDocument + reloads documents), failure path (sets uploadError, re-throws), loading state transitions

## Dev Notes

### Architecture

- **Presentation-only component**: `DocumentChecklist.vue` makes no API calls — all data flows through props, all actions flow out as emitted events
- **Permission functions**: pure exported functions in `useDocumentPermissions.ts` — no Vue reactivity, just logic; composable wrapper for convenience
- **Store upload action**: `requests.store.ts` gets `uploading` / `uploadError` state + `uploadDocument(id, file)` action that calls `useRequests().uploadDocument()` then reloads documents
- **Page owns state**: `requests/[id]/index.vue` owns `downloadingIds`, `downloadErrors`, `customsDownloading`, `customsDownloadError` — these remain on the page and are passed as props to the component
- **Test pattern**: pure-logic extraction (no component mounting, no `@vue/test-utils`) — identical to `AuditTimeline.test.ts` and `WorkflowTimeline.test.ts`

### Permission Matrix Reference (from Story 4.3 backend policies)

| Role | REQUEST_DOC | SWIFT | CUSTOMS PDF |
|------|-------------|-------|-------------|
| DATA_ENTRY | ✓ (show button) | ✗ (hide) | ✗ (hide) |
| BANK_REVIEWER | ✓ | ✓ | ✓ |
| SWIFT_OFFICER | ✓ | ✓ | ✗ |
| SUPPORT_COMMITTEE | ✓ | ✗ | ✗ |
| EXECUTIVE_MEMBER | ✓ | ✓ | ✗ |
| COMMITTEE_DIRECTOR | ✓ | ✓ | ✓ |
| CBY_ADMIN | ✓ | ✓ | ✓ |

> Note: The frontend hides the download button when the role is not permitted to avoid needless 403 round-trips. The backend always enforces the real permission check regardless.

### Existing Code to Extend

- `frontend/app/composables/useRequests.ts` — `uploadDocument(requestId, file, label)` and `fetchRequestDocuments(id)` already exist; do **not** change their signatures
- `frontend/app/stores/requests.store.ts` — add `uploading`, `uploadError` state and `uploadDocument` action
- `frontend/app/pages/requests/[id]/index.vue` — Documents tab section (lines ~550–595) to be replaced with `<DocumentChecklist />`; `downloadDocument()` and `downloadCustomsDeclaration()` functions remain on the page
- `frontend/app/types/models.ts` — `RequestDocument.type` is `string | null` (`'REQUEST_DOC'` or `'SWIFT'`); `CustomsDeclarationSummary` already defined (imported from `ImportRequest.customs_declaration`)

### Key Types

```typescript
// RequestDocument (already in models.ts)
interface RequestDocument {
  id: number
  type: string | null          // 'REQUEST_DOC' | 'SWIFT' | null
  original_filename: string
  mime_type: string | null
  size_bytes: number
  checksum: string
  uploaded_by: number
  uploaded_by_name: string | null
  uploaded_at: string
  download_url: string
}

// CustomsDeclarationSummary (already in models.ts)
interface CustomsDeclarationSummary {
  id: number
  declaration_number: string
  issued_at: string
  issued_by: number | null
  issuer: { id: number; name: string } | null
  download_url: string
}
```

### Upload Flow

1. `DocumentChecklist` emits `upload(file: File)`
2. `requests/[id]/index.vue` receives event, calls `requestsStore.uploadDocument(id, file)`
3. Store action calls `useRequests().uploadDocument(requestId, file, file.name)` then `this.loadDocuments(id)`
4. On success: document list refreshes; on error: `uploadError` is set, error shown in component

### Upload File Constraint

The file picker must set `accept="application/pdf"` to enforce PDF-only (per platform-wide rule). The backend also validates PDF on its side.

### SWIFT Badge Design

```css
.doc-badge--swift {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 4px;
  background: #32ade6;
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.03em;
}
```

### Customs Declaration Row

The customs declaration row should look identical to a document row. Use a distinct label "بيان جمركي" in place of a type badge. The filename shown is `declaration_number` (e.g. "CUST-2026-001"). The date shown is `issued_at`.

### Deferred Note (from Story 2.2 code review)

- **Double API call on fetchRequestDocuments** — `fetchRequestDocuments` makes a second `GET /api/requests/{id}` call instead of reusing `loadRequest` response. This is pre-existing deferred architectural issue; do not attempt to fix it in this story.

### Previous Patterns to Follow

- Component test: see `AuditTimeline.test.ts` and `WorkflowTimeline.test.ts` for pure-logic extraction pattern
- Store action test: see `requests.store.history.test.ts` for the mock pattern (`vi.mock('../../../composables/useRequests', ...)`)
- Store state: follow `loadDocuments` action pattern (loading flag, error string, finally block)

## Dev Agent Record

### Implementation Plan

1. Create `useDocumentPermissions.ts` with 4 pure permission functions covering the role/type/status matrix.
2. Create `DocumentChecklist.vue` as a presentation-only component (no API calls) — all data via props, actions via emits.
3. Add `uploading`/`uploadError` state + `uploadDocument` action to `requests.store.ts`.
4. Wire component into the Documents tab of `requests/[id]/index.vue`; clean up orphaned CSS.
5. Write exhaustive unit tests for all 3 units using the project's pure-logic extraction pattern (no component mounting).

### Debug Log

| # | File | Issue | Resolution |
|---|------|-------|------------|
| 1 | DocumentChecklist.vue | Vue composables not auto-imported | Added explicit `import { ref, computed } from 'vue'` per project convention |
| 2 | index.vue | `.docs-list` / `.doc-item` etc. CSS orphaned after replacing inline markup | Removed all orphaned classes; kept `.docs-error` which is still used in the Overview tab's customs section |

### Completion Notes

All 6 ACs implemented and tested:
- AC1: Document list with type label, filename, size, uploader, date ✅
- AC2: Role-gated download buttons per document type (REQUEST_DOC/SWIFT/customs) ✅
- AC3: PDF-only upload for DATA_ENTRY on DRAFT/DRAFT_REJECTED_INTERNAL ✅
- AC4: Locked note for DATA_ENTRY on non-editable requests; no upload section for other roles ✅
- AC5: SWIFT badge in `#32ade6` ✅
- AC6: Customs declaration row with role-gated download ✅

81 new tests added across 3 test files; 655/655 tests passing with zero regressions.

## File List

### New Files

- `frontend/app/composables/useDocumentPermissions.ts`
- `frontend/app/components/requests/DocumentChecklist.vue`
- `frontend/app/tests/unit/composables/useDocumentPermissions.test.ts`
- `frontend/app/tests/unit/components/DocumentChecklist.test.ts`
- `frontend/app/tests/unit/stores/requests.store.upload.test.ts`

### Modified Files

- `frontend/app/stores/requests.store.ts` — added `uploading`/`uploadError` state + `uploadDocument` action
- `frontend/app/pages/requests/[id]/index.vue` — imported `DocumentChecklist`, replaced inline documents markup, added handlers, removed orphaned CSS

## Change Log

| Date | Change |
|------|--------|
| 2026-05-17 | Story created from Epic 4 spec |
| 2026-05-17 | Story implemented: useDocumentPermissions composable, DocumentChecklist component, store upload action, page wiring, 81 unit tests |
