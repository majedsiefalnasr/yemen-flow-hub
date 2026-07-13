# DATA_ENTRY Manual System Test

Role: Bank data entry officer.  
Arabic label: مدخل بيانات البنك.  
Primary test viewpoint: create, edit, attach documents, submit, correct returned requests, and track simplified business status.

## Notes

- Data Entry sees own-bank requests only.
- Data Entry should see simplified business statuses rather than internal CBY operational detail.
- Editing is allowed only before internal approval or when the request is returned to Data Entry.
- Data Entry cannot approve, reject, claim, vote, upload SWIFT, or finalize.

## Sequence

### Bank A primary creator: `entry-a`

1. Log in as `entry-a`.
2. Verify sidebar includes dashboard, requests, new request, notifications, and settings.
3. Open `/dashboard`.
4. Verify dashboard focuses on drafts, returned requests, submitted/processing requests, completed, and rejected.
5. Open `/workflows/new`.
6. Fill request fields: currency, amount, supplier, goods description, port of entry, and notes.
7. Save as draft.
8. Expected: request exists in `DRAFT`; creator is `entry-a`.
9. Record the request number as `REQ-A-ENTRY-A`.
10. Open request edit page.
11. Upload valid request PDF.
12. Attempt to upload a non-PDF.
13. Expected: non-PDF is rejected.
14. Update at least one field and save.
15. Submit request.
16. Expected: status becomes `SUBMITTED`; edit/delete/upload mutation controls disappear.
17. Open `/workflows`.
18. Verify `REQ-A-ENTRY-A` appears under submitted/processing business bucket with simplified status.

### Bank A second data entry user: `entry-a-2`

1. Log out, then log in as `entry-a-2`.
2. Open `/workflows`.
3. Search for `REQ-A-ENTRY-A`.
4. Expected: `entry-a-2` can see the Bank A request because requests belong to the bank, not the individual user.
5. Verify `entry-a-2` cannot edit `REQ-A-ENTRY-A` while it is `SUBMITTED`.
6. Open `/workflows/new`.
7. Create and save a second Bank A draft request.
8. Record the request number as `REQ-A-ENTRY-A-2`.
9. Submit `REQ-A-ENTRY-A-2`.
10. Log out, then log in again as `entry-a`.
11. Search for `REQ-A-ENTRY-A-2`.
12. Expected: `entry-a` can see the second Bank A request.

### Bank B isolation user: `entry-b`

1. Log out, then log in as `entry-b`.
2. Open `/workflows/new`.
3. Create and save a Bank B draft request.
4. Record the request number as `REQ-B-ENTRY-B`.
5. Submit the request if the Bank B reviewer path is available; otherwise keep it as a Bank B draft for visibility checks.
6. Log out, then log in as `entry-b-2`.
7. Search for `REQ-B-ENTRY-B`.
8. Expected: `entry-b-2` can see the Bank B request because requests belong to the bank, not the individual user.
9. Open `/workflows/new`.
10. Create and save a second Bank B draft request.
11. Record the request number as `REQ-B-ENTRY-B-2`.
12. Search for `REQ-A-ENTRY-A`.
13. Expected: no result.
14. Search for `REQ-A-ENTRY-A-2`.
15. Expected: no result.
16. Log out, then log in as `entry-a`.
17. Search for `REQ-B-ENTRY-B`.
18. Expected: no result.
19. Search for `REQ-B-ENTRY-B-2`.
20. Expected: no result.
21. Log out, then log in as `entry-a-2`.
22. Search for `REQ-B-ENTRY-B`.
23. Expected: no result.
24. Search for `REQ-B-ENTRY-B-2`.
25. Expected: no result.

### Ongoing workflow tracking

1. Track `REQ-A-ENTRY-A` as it moves through bank, support, SWIFT, executive, and final states.
2. Verify `entry-a` and `entry-a-2` see simplified labels such as submitted, processing at CBY, completed, or rejected.
3. Verify neither Bank A Data Entry user sees Bank B request data at any workflow stage.
4. Verify neither Bank B Data Entry user sees Bank A request data at any workflow stage.

## Returned Request Correction Path

1. Use a request returned by Bank Reviewer or Support Committee.
2. Log in as `entry-a` for `REQ-A-ENTRY-A`, or as `entry-a-2` for `REQ-A-ENTRY-A-2`.
3. Open returned queue.
4. Verify reason/comments are visible.
5. Edit required fields and/or upload corrected PDF.
6. Resubmit.
7. Expected: status returns to `SUBMITTED` or next configured bank-review state; history records resubmission.

## Terminal Rejection Path

1. Open a request in `BANK_REJECTED`, `SUPPORT_REJECTED` if finalized, or `EXECUTIVE_REJECTED`.
2. Verify rejection reason and terminal status are visible.
3. Confirm edit, resubmit, delete, and upload controls are absent.
4. Verify notification opens a summary dialog first if notification behavior is being tested.

## Negative Tests

- Data Entry cannot see Bank B requests.
- `entry-b` and `entry-b-2` cannot see Bank A requests.
- `entry-a` and `entry-a-2` can see each other's Bank A requests but cannot see Bank B requests.
- `entry-b` and `entry-b-2` can see each other's Bank B requests but cannot see Bank A requests.
- Data Entry cannot edit after `SUBMITTED` unless returned.
- Data Entry cannot approve as Bank Reviewer.
- Data Entry cannot upload SWIFT documents.
- Data Entry cannot claim support review.
- Data Entry cannot vote or see voting controls.
- Data Entry cannot download restricted CBY-only final documents if policy forbids it.

## Evidence

Capture screenshots of:

- New request form.
- Draft saved state.
- Valid PDF upload.
- Non-PDF rejection.
- Submitted read-only state.
- Returned correction form and resubmission.
- Terminal rejection read-only state.
- Request history entries for create, update, upload, submit, return, and resubmit.
