# CBY_ADMIN Manual System Test

Role: Central system administrator.  
Arabic label: مدير النظام.  
Primary test viewpoint: system governance, users, banks, audit, reports, and read-only workflow oversight.

## Notes

- `CBY_ADMIN` has broad visibility, but must not act as a workflow super-user.
- This role must not approve bank review, claim support review, upload SWIFT files, vote, open/close voting, or finalize Director decisions.
- The strongest pass condition is oversight without unauthorized workflow mutation.

## Sequence

1. Log in as `admin-cby`.
2. Verify sidebar includes dashboard, requests, merchants, reports, audit, CBY staff, banks/entities, document rules, permissions, notifications, and settings.
3. Open `/dashboard`.
4. Verify system health cards, workflow pressure, bank risk signals, executive voting oversight, compliance/audit signals, and critical events feed load without cross-role action buttons.
5. Open `/admin/cby-staff`.
6. Create or edit CBY staff test users for `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, and `SWIFT_OFFICER` if needed.
7. Verify bank-managed roles are not incorrectly provisioned as CBY governance roles unless the current admin surface explicitly supports that role assignment.
8. Open `/admin/entities`.
9. Verify Bank A and Bank B are visible to CBY Admin, with role coverage and risk indicators.
10. Open `/requests`.
11. Search for the primary request number created by `entry-a`.
12. Verify all workflow statuses can be filtered and viewed with full canonical labels.
13. Open request detail.
14. Verify workflow progress, timeline, documents, voting, parties, and audit tabs are visible as oversight.
15. Confirm no workflow action buttons appear for bank approval, support claim/review, SWIFT upload, voting, or Director finalization.
16. Open `/audit`.
17. Filter by the primary request number and verify every transition event appears with actor, role, action, from-status, to-status, timestamp, and notes where applicable.
18. Open `/reports`.
19. Verify export/report filters work and do not expose mutation controls.
20. Open `/admin/roles`.
21. Verify the role authority matrix matches the canonical role boundaries.

## Workflow Checkpoints

During the full request workflow, return to this role after each major state:

| Status checkpoint | Expected CBY Admin view |
| --- | --- |
| `SUBMITTED` | Visible in global requests; read-only. |
| `BANK_APPROVED` / `SUPPORT_REVIEW_PENDING` | Visible in CBY pressure/support queue reporting; no claim button. |
| `SUPPORT_REVIEW_IN_PROGRESS` | Claim owner is visible; no release/approve/reject controls unless explicitly administrative and audited. |
| `WAITING_FOR_SWIFT` | SWIFT bottleneck is visible; no upload control. |
| `EXECUTIVE_VOTING_OPEN` | Voting state visible; no vote/open/close controls. |
| `EXECUTIVE_APPROVED` / `EXECUTIVE_REJECTED` | Final decision visible; no finalization controls. |
| `COMPLETED` | Completed state visible; audit trail complete. |

## Negative Tests

- Attempt to navigate directly to a workflow action route or stale action button if available. Expected: UI should not expose it; backend rejects with authorization/immutable-state response.
- Verify `CBY_ADMIN` cannot submit an executive vote.
- Verify `CBY_ADMIN` cannot upload a SWIFT document.
- Verify `CBY_ADMIN` cannot claim a support review.
- Verify changing system settings creates an audit event.

## Evidence

Capture screenshots of:

- Dashboard oversight widgets.
- Request list with canonical status filter.
- Request detail with no workflow mutation controls.
- Audit event detail drawer or table row for each transition.
- Role/permission matrix.
