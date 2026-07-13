# BANK_ADMIN Manual System Test

Role: Bank administrator.  
Arabic label: مسؤول البنك.  
Primary test viewpoint: own-bank administration, staff/merchant management, bank portfolio oversight, and read-only workflow visibility.

## Notes

- Bank Admin is not a workflow decision role.
- Bank Admin must not approve, reject, return, claim, vote, upload SWIFT as a workflow actor, or finalize decisions.
- Own-bank scope is mandatory.

## Sequence

1. Log in as `bank-admin-a`.
2. Verify sidebar includes dashboard, requests, notifications, merchants, staff, reports, and settings.
3. Open `/dashboard`.
4. Verify bank-level overview cards, operational health strip if applicable, quick actions, trend chart, and recent bank requests.
5. Confirm no workflow decision buttons appear on dashboard.
6. Open `/staff`.
7. Verify only Bank A staff are visible.
8. Create or edit a bank staff user only in bank-manageable roles such as `DATA_ENTRY` and `BANK_REVIEWER`.
9. Verify CBY roles cannot be assigned from this surface.
10. Open `/merchants`.
11. Verify merchant CRUD is scoped to Bank A.
12. Open `/workflows`.
13. Verify own-bank request portfolio appears across all relevant statuses with read-only management controls.
14. Search for `REQ-A-ENTRY-A` and `REQ-A-ENTRY-A-2`.
15. Expected: both Bank A requests are visible.
16. Search for `REQ-B-ENTRY-B`.
17. Expected: no result.
18. Open the primary request detail at each major workflow checkpoint.
19. Verify status, timeline, documents, and current owner are visible.
20. Verify no approve/reject/return/vote/claim/SWIFT/finalize controls are rendered.
21. Open `/reports`.
22. Verify bank-level reports/export are scoped to Bank A.

## Bank B Mirror Scope Test

1. Log out, then log in as `bank-admin-b`.
2. Open `/staff`.
3. Verify only Bank B staff are visible, including `entry-b`, `entry-b-2`, `reviewer-b`, `reviewer-b-2`, and `swift-bank-b` if SWIFT staff are shown to bank admins.
4. Verify `entry-a`, `entry-a-2`, `reviewer-a`, `reviewer-a-2`, and `swift-bank-a` are not visible.
5. Open `/merchants`.
6. Verify merchant CRUD is scoped to Bank B.
7. Open `/workflows`.
8. Search for `REQ-B-ENTRY-B` and `REQ-B-ENTRY-B-2`.
9. Expected: both Bank B requests are visible.
10. Search for `REQ-A-ENTRY-A` and `REQ-A-ENTRY-A-2`.
11. Expected: no result.
12. Open `/reports`.
13. Verify bank-level reports/export are scoped to Bank B.

## Workflow Visibility Checkpoints

| Request state | Expected Bank Admin behavior |
| --- | --- |
| `DRAFT` | Visible if created by own bank; administrative visibility only. |
| `SUBMITTED` / `BANK_REVIEW` | Visible; no review controls. |
| `BANK_APPROVED` through CBY processing | Visible as read-only portfolio tracking. |
| `WAITING_FOR_SWIFT` / `SWIFT_UPLOADED` | Visible if own bank; no upload control unless current implementation intentionally gives SWIFT role separately. |
| `EXECUTIVE_APPROVED` / `EXECUTIVE_REJECTED` | Visible as final outcome tracking. |
| `COMPLETED` | Visible in completed portfolio/reporting. |

## Negative Tests

- Bank Admin cannot approve a request as Bank Reviewer.
- Bank Admin cannot return support-rejected requests to Data Entry.
- Bank Admin cannot edit Data Entry returned requests unless using an explicitly supported fallback draft flow; even then, it must not bypass segregation of duties.
- Bank Admin cannot see Bank B staff, merchants, requests, documents, reports, or notifications.
- `bank-admin-b` cannot see Bank A staff, merchants, requests, documents, reports, or notifications.
- Bank Admin cannot assign `SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, or `CBY_ADMIN`.

## Evidence

Capture screenshots of:

- Bank dashboard.
- Staff role assignment restrictions.
- Bank-scoped requests list.
- Bank B search returning no data.
- Request detail without workflow action controls.
- Bank report export/filter state.
