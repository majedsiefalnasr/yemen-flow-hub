# BANK_REVIEWER Manual System Test

Role: Bank internal reviewer.  
Arabic label: مراجع داخلي بالبنك.  
Primary test viewpoint: bank-side decision gate, segregation of duties, downstream tracking, and post-support rejection handling.

## Notes

- Reviewer scope is own bank only.
- The request creator must not approve or reject their own request.
- `BANK_REJECTED` is terminal and irreversible.
- Returned requests become Data Entry work; reviewer does not edit them.

## Sequence

1. Log in as `reviewer-a`.
2. Verify sidebar includes dashboard, requests, notifications, and settings only.
3. Open `/dashboard`.
4. Verify pending review queue and support-rejection queue are prominent.
5. Open `/workflows`.
6. Verify tabs such as pending review, support rejected, bank returned, support returned, at CBY, completed, and rejected.
7. Open the request submitted by `entry-a` in `SUBMITTED`.
8. Start review if the UI has a separate start action.
9. Expected: status becomes `BANK_REVIEW`.
10. Verify request documents, intake fields, timeline, and creator identity are visible.
11. Approve the request with required notes if prompted.
12. Expected: status becomes `BANK_APPROVED` or `SUPPORT_REVIEW_PENDING`; request becomes locked for bank editing.
13. Return later after support/SWIFT/voting stages and verify downstream statuses remain visible as read-only.
14. Log out, then log in as `reviewer-a-2`.
15. Search for the same Bank A request.
16. Expected: `reviewer-a-2` can see the Bank A submitted/reviewed request because review visibility is bank-scoped.
17. Search for `REQ-B-ENTRY-B` from Bank B.
18. Expected: no result.

## Bank B Mirror Review Scope Test

1. Log out, then log in as `reviewer-b`.
2. Open `/workflows`.
3. Search for `REQ-B-ENTRY-B`.
4. Expected: Bank B submitted/reviewable request is visible.
5. Search for `REQ-A-ENTRY-A` and `REQ-A-ENTRY-A-2`.
6. Expected: no result.
7. Log out, then log in as `reviewer-b-2`.
8. Search for `REQ-B-ENTRY-B`.
9. Expected: `reviewer-b-2` can see the Bank B submitted/reviewed request because review visibility is bank-scoped.

## Bank Return Path

1. Create a second request as `entry-a`.
2. Submit it.
3. Log in as `reviewer-a`.
4. Open the request and choose return to Data Entry.
5. Enter mandatory reason.
6. Expected: status becomes `BANK_RETURNED` or the current editable returned state.
7. Log in as `entry-a`.
8. Verify the request appears in returned queue, can be edited, and can be resubmitted.

## Terminal Bank Rejection Path

1. Create and submit a third request.
2. Log in as `reviewer-a`.
3. Reject terminally with mandatory comment.
4. Expected: status becomes `BANK_REJECTED`.
5. Verify Data Entry receives notification and cannot edit, reopen, or resubmit.
6. Verify request history clearly states terminal bank rejection.

## Post-Support Rejection Path

1. Use a request rejected or returned by `SUPPORT_COMMITTEE`.
2. Log in as `reviewer-a`.
3. Open support-rejected queue.
4. Choose either keep/finalize rejection or return to Data Entry.
5. If returned, expected status becomes `BANK_RETURNED`, `SUPPORT_RETURNED`, or current editable returned state according to implementation.
6. If finalized, expected request remains rejected and read-only.

## Segregation-of-Duties Test

1. If a reviewer-capable account created a request through an operational fallback, log in as that same user.
2. Attempt to review that same request.
3. Expected: decision controls are absent; forced backend action is rejected.
4. Log in as `reviewer-a-2`.
5. Verify a different eligible Bank A reviewer can perform the review action if the request is otherwise valid.

## Negative Tests

- Bank Reviewer cannot see Bank B requests.
- Bank B reviewers cannot see Bank A requests.
- Bank Reviewer cannot edit request business fields after submission unless returned to Data Entry and assigned to that role.
- Bank Reviewer cannot claim support review.
- Bank Reviewer cannot upload SWIFT.
- Bank Reviewer cannot vote or finalize executive decision.
- Bank Reviewer cannot reverse `BANK_REJECTED`.

## Evidence

Capture screenshots of:

- Pending review queue.
- Start review state.
- Approve dialog and resulting status.
- Return dialog and returned Data Entry state.
- Terminal rejection dialog and immutable state.
- Post-support rejection handling.
- Audit/history entries for every bank reviewer action.
