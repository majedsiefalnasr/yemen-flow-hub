# COMMITTEE_DIRECTOR Manual System Test

Role: Executive committee director.  
Arabic label: مدير اللجنة التنفيذية.  
Primary test viewpoint: executive voting governance, tie resolution, final decision, and external FX confirmation completion.

## Notes

- The Director can manage voting sessions and also vote as a normal executive member.
- The Director does not perform bank review, support review, or SWIFT upload.
- New UI copy should use external FX confirmation terminology: `تأكيد المصارفة الخارجية`.

## Sequence

1. Log in as `director-cby`.
2. Verify sidebar includes dashboard, requests, external FX confirmation, reports, audit, notifications, and settings.
3. Open `/dashboard`.
4. Verify Director work queues prioritize pending voting decisions, sessions ready to close, ties, and external FX confirmation items.
5. Open `/workflows`.
6. Verify Director-aware tabs for pending/open/closed voting, approved decisions, rejected decisions, FX pending, completed, and all.
7. Open a request in `WAITING_FOR_VOTING_OPEN` or equivalent ready-to-open state.
8. Verify request detail shows voting controls but no support claim, bank decision, or SWIFT upload controls.
9. Open voting.
10. Expected: status becomes `EXECUTIVE_VOTING_OPEN`, voting session metadata is visible, and audit/history records the opening actor as `COMMITTEE_DIRECTOR`.
11. Submit the Director vote if the UI allows Director-as-member voting.
12. Expected: vote is recorded once and cannot be changed.
13. Wait for at least one executive member to vote from [executive-member.md](./executive-member.md).
14. Close voting.
15. Expected: status becomes `EXECUTIVE_VOTING_CLOSED`; non-voting members are recorded as `AUTO_ABSTAIN_TIMEOUT`.
16. If results are tied, verify Director tie-resolution behavior follows the Director vote.
17. Finalize the executive decision.
18. If approved, expected status becomes `EXECUTIVE_APPROVED` and the request enters the external FX confirmation completion path.
19. If rejected, expected status becomes `EXECUTIVE_REJECTED` and the request is terminal and immutable.
20. For approved requests that reach external FX confirmation, open `/customs` or the current external FX route.
21. Generate/preview the external FX confirmation.
22. Complete the external FX confirmation.
23. Expected: request reaches `FX_CONFIRMATION_PENDING`, `CUSTOMS_DECLARATION_ISSUED`, or `COMPLETED` according to current implementation, with audit/history entries for generation and completion.

## Alternate Paths

- Open a voting session and close it before all members vote; confirm missing votes become `AUTO_ABSTAIN_TIMEOUT`.
- Create a split vote and verify tie resolution.
- Finalize rejection and verify no later SWIFT or FX completion action is available.
- Attempt to finalize before voting is closed. Expected: blocked.

## Negative Tests

- Director cannot edit request business fields after bank approval.
- Director cannot upload request intake documents or SWIFT PDFs.
- Director cannot claim support review.
- Director cannot open voting before SWIFT/voting readiness is reached.
- Director cannot change a submitted vote after closure.
- Director cannot complete external FX confirmation on an executive-rejected request.

## Evidence

Capture screenshots of:

- Voting open action and resulting status.
- Vote submission state for Director.
- Voting close summary with auto-abstain if applicable.
- Final decision confirmation.
- External FX confirmation preview/completion.
- Request history and audit logs for open, close, vote, final decision, and FX completion.
