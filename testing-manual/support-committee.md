# SUPPORT_COMMITTEE Manual System Test

Role: Support committee member.  
Arabic label: عضو لجنة المساندة.  
Primary test viewpoint: claim-aware CBY support review queue.

## Notes

- Support review claiming is temporary and presence-based.
- Claim TTL is 15 minutes of inactivity.
- Heartbeat should run every 60 seconds while the reviewer is active on the request page.
- Only the current claim holder can approve, reject, or return the claimed request.

## Sequence

1. Log in as `support-a-cby`.
2. Verify sidebar includes dashboard, requests, notifications, and settings only for support work.
3. Open `/dashboard`.
4. Verify active-claim strip, waiting-for-claim count, active-by-me count, claimed-by-others count, and support queue table.
5. Open `/workflows`.
6. Verify claim-aware tabs: waiting, my claims, in progress/claimed by others, approved, returned, rejected, and all.
7. Open a request in `SUPPORT_REVIEW_PENDING`.
8. Claim the request.
9. Expected: status becomes `SUPPORT_REVIEW_IN_PROGRESS`, claim owner is `support-a-cby`, and heartbeat starts.
10. In a second browser session, log in as `support-b-cby`.
11. Open the same request.
12. Expected: request is visible, claim owner is visible, but approve/reject/return controls are absent for `support-b-cby`.
13. Return to `support-a-cby`.
14. Review documents and request details.
15. Approve the request.
16. Expected: status becomes `SUPPORT_APPROVED` and then enters the SWIFT handoff state according to implementation.
17. Repeat with a second request and reject/return with mandatory reason.
18. Expected: bank reviewer sees the request in the post-support rejection/return queue.
19. Release a claim manually or navigate away if release is implemented on unload.
20. Expected: claim is cleared and another support member can claim.

## Claim Timeout Test

1. Claim a request as `support-a-cby`.
2. Stop activity/heartbeat for more than 15 minutes, or simulate TTL expiry in the test environment.
3. Refresh the queue as `support-b-cby`.
4. Expected: request is claimable again, status returns to `SUPPORT_REVIEW_PENDING` if the implementation stores in-progress only during active claim.

## Negative Tests

- Support member cannot claim a request already claimed by another active reviewer.
- Support member cannot approve/reject without owning the claim.
- Support member cannot review requests outside support-stage statuses.
- Support member cannot upload SWIFT.
- Support member cannot vote or open voting.
- Support member cannot edit request business fields after bank approval.
- Non-PDF document upload attempts are rejected if any support upload surface exists.

## Evidence

Capture screenshots of:

- Waiting queue.
- Claimed-by-me row.
- Claimed-by-other row from second support user.
- Approve/reject/return dialog with reason.
- Request history and audit entries for claim, heartbeat if audited, release, approval, rejection, or return.
