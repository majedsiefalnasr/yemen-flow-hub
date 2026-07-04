# SWIFT_OFFICER Manual System Test

Role: Bank SWIFT officer.  
Arabic label: موظف السويفت بالبنك.  
Primary test viewpoint: upload required SWIFT-stage documents after support approval and before executive voting.

## Notes

- Scope is own bank only.
- SWIFT Officer does not review, approve, vote, claim support review, or finalize requests.
- The SWIFT stage is document-gated. Submission must remain blocked until all required PDFs are uploaded.
- In the canonical full workflow, SWIFT upload happens before executive voting opens.

## Sequence

1. Log in as `swift-bank-a`.
2. Verify sidebar includes dashboard, requests, notifications, and settings.
3. Open `/dashboard`.
4. Verify the primary queue is waiting for SWIFT upload and does not include bank-wide analytics or decision controls.
5. Open `/workflows`.
6. Verify tabs such as pending SWIFT, SWIFT done, completed, rejected, and all.
7. Open a request in `WAITING_FOR_SWIFT` or equivalent ready state.
8. Verify request detail shows locked business data and a clear SWIFT-ready banner or upload shortcut.
9. On the request detail page (`/workflows/instances/[id]`), locate the document upload section for the SWIFT stage.
10. Verify both required document slots are shown: SWIFT PDF and external FX confirmation request PDF.
11. Attempt to submit with no files.
12. Expected: submit is disabled or blocked with a clear inline reason.
13. Upload a non-PDF file.
14. Expected: rejected with PDF-only validation.
15. Upload only the SWIFT PDF.
16. Expected: stage submission remains blocked because the FX confirmation request PDF is missing.
17. Upload the FX confirmation request PDF.
18. Submit SWIFT stage.
19. Expected: status becomes `SWIFT_UPLOADED` or the configured voting-ready state, uploaded documents become read-only, and replacement is blocked.
20. Return to `/workflows`.
21. Verify request moves from pending SWIFT to SWIFT done/post-upload tab.

## Organization-Scope Test

1. Create or identify `REQ-B-ENTRY-B` in SWIFT stage, or use any Bank B request that has reached SWIFT stage.
2. Log in as `swift-bank-a`.
3. Search for the Bank B request number.
4. Expected: no result and no direct detail access.
5. Search for a Bank A SWIFT-stage request such as `REQ-A-ENTRY-A`.
6. Expected: visible if it has reached SWIFT stage.
7. Log out, then log in as `swift-bank-b`.
8. Search for the Bank B SWIFT-stage request.
9. Expected: visible if it has reached SWIFT stage.
10. Search for the Bank A SWIFT-stage request such as `REQ-A-ENTRY-A`.
11. Expected: no result and no direct detail access.

## Negative Tests

- SWIFT Officer cannot upload before `WAITING_FOR_SWIFT`.
- SWIFT Officer cannot replace SWIFT documents after submission.
- SWIFT Officer cannot edit request amount, supplier, goods, port, or notes.
- SWIFT Officer cannot see other-bank requests.
- `swift-bank-b` cannot see Bank A SWIFT-stage requests.
- SWIFT Officer cannot open voting or vote.
- SWIFT Officer cannot access external FX confirmation completion PDF if restricted by policy.

## Evidence

Capture screenshots of:

- SWIFT queue.
- Upload page with both document slots.
- Blocked submit with missing file.
- Non-PDF rejection.
- Successful upload and final read-only document state.
- Audit/history entries for uploads and status transition.
