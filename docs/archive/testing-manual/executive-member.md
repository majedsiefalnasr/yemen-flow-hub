# EXECUTIVE_MEMBER Manual System Test

Role: Executive committee member.  
Arabic label: عضو اللجنة التنفيذية.  
Primary test viewpoint: review voting materials and cast one immutable vote during an open voting session.

## Notes

- Executive Members vote only during `EXECUTIVE_VOTING_OPEN`.
- They do not open/close sessions, finalize decisions, upload SWIFT, claim support review, or edit requests.
- One vote per member; vote changes are not allowed after submission.

## Sequence

1. Log in as `exec-a-cby`.
2. Verify sidebar includes dashboard, requests, reports, notifications, and settings, without admin or bank operation pages.
3. Open `/dashboard`.
4. Verify the primary queue is pending votes or active voting sessions.
5. Open `/workflows`.
6. Verify voting-aware tabs such as needs my vote, voted by me, waiting to open, voting open, voting closed, approved, rejected, and post-approval.
7. Open a request in `EXECUTIVE_VOTING_OPEN`.
8. Verify request detail includes request data, request documents, SWIFT document if allowed, voting summary, and a vote panel.
9. Submit `APPROVE` with justification.
10. Expected: vote is recorded, user sees voted state, and the vote action cannot be submitted again.
11. Log out and log in as `exec-b-cby`.
12. Submit `REJECT` or `ABSTAIN` on the same request to exercise mixed voting outcomes.
13. Return after Director closes voting.
14. Verify the request appears as voted/closed/read-only and no further vote action is available.

## Alternate Paths

- Submit `ABSTAIN` manually and verify it is distinct from `AUTO_ABSTAIN_TIMEOUT`.
- Do not vote before Director closes the session; verify the member is recorded as `AUTO_ABSTAIN_TIMEOUT`.
- Vote on a request that later becomes `EXECUTIVE_REJECTED`; verify final state is terminal and visible.

## Negative Tests

- Executive Member cannot vote before `EXECUTIVE_VOTING_OPEN`.
- Executive Member cannot vote after `EXECUTIVE_VOTING_CLOSED`.
- Executive Member cannot vote twice or change vote.
- Executive Member cannot open or close voting.
- Executive Member cannot finalize decision.
- Executive Member cannot upload SWIFT or edit request fields.
- Executive Member cannot see bank-only staff/merchant administration.

## Evidence

Capture screenshots of:

- Pending vote queue.
- Vote form before submission.
- Voted state after submission.
- Closed voting state.
- Audit/history entry for the vote.
