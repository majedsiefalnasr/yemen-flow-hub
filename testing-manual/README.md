# Yemen Flow Hub Manual System Test

This directory contains a role-by-role manual system test for Yemen Flow Hub. It is written for full-system validation from the viewpoint of each operational role, with special focus on the import financing request workflow.

## Files

| File | Role |
| --- | --- |
| [cby-admin.md](./cby-admin.md) | CBY system administrator |
| [committee-director.md](./committee-director.md) | Executive committee director |
| [executive-member.md](./executive-member.md) | Executive committee member |
| [support-committee.md](./support-committee.md) | Support committee member |
| [swift-officer.md](./swift-officer.md) | Bank SWIFT officer |
| [bank-admin.md](./bank-admin.md) | Bank administrator |
| [bank-reviewer.md](./bank-reviewer.md) | Bank internal reviewer |
| [data-entry.md](./data-entry.md) | Bank data entry officer |

## Test Objective

Validate that Yemen Flow Hub behaves as an internal, audit-sensitive, role-scoped workflow platform:

- Each role sees only the correct navigation, queues, request data, documents, and actions.
- Request visibility is organization-scoped for bank roles and role-scoped for CBY roles.
- The main workflow progresses only through valid transitions.
- Forbidden controls are not rendered, not merely disabled.
- Backend rejection still blocks forbidden actions if a tester reaches an endpoint or stale UI state.
- Every workflow transition appears in request history and audit logs with actor, role, timestamp, from-status, to-status, action, and notes where applicable.
- PDF-only document validation is enforced for all uploads.
- Terminal and locked states cannot be edited or reopened.

## Required Test Users

Create or identify active users before starting the test run:

| Alias | Role | Organization |
| --- | --- | --- |
| `admin-cby` | `CBY_ADMIN` | CBY |
| `director-cby` | `COMMITTEE_DIRECTOR` | CBY |
| `exec-a-cby` | `EXECUTIVE_MEMBER` | CBY |
| `exec-b-cby` | `EXECUTIVE_MEMBER` | CBY |
| `support-a-cby` | `SUPPORT_COMMITTEE` | CBY |
| `support-b-cby` | `SUPPORT_COMMITTEE` | CBY |
| `swift-bank-a` | `SWIFT_OFFICER` | Bank A |
| `swift-bank-b` | `SWIFT_OFFICER` | Bank B |
| `bank-admin-a` | `BANK_ADMIN` | Bank A |
| `bank-admin-b` | `BANK_ADMIN` | Bank B |
| `reviewer-a` | `BANK_REVIEWER` | Bank A |
| `reviewer-a-2` | `BANK_REVIEWER` | Bank A |
| `reviewer-b` | `BANK_REVIEWER` | Bank B |
| `reviewer-b-2` | `BANK_REVIEWER` | Bank B |
| `entry-a` | `DATA_ENTRY` | Bank A |
| `entry-a-2` | `DATA_ENTRY` | Bank A |
| `entry-b` | `DATA_ENTRY` | Bank B |
| `entry-b-2` | `DATA_ENTRY` | Bank B |

These aliases are canonical for this manual. Use the exact same alias names in every role test note and evidence record.

Use Bank B for negative organization-scope checks. Bank B data must never appear to Bank A users, and Bank A data must never appear to Bank B users.

## Required Test Data

Prepare the following artifacts:

- A valid request document PDF.
- A valid SWIFT PDF.
- A valid external FX confirmation request PDF.
- A non-PDF file for upload rejection checks.
- Bank A and Bank B as separate active banks.
- A merchant or supplier record for Bank A.
- A merchant or supplier record for Bank B.
- At least one request created by `entry-a` in Bank A.
- At least one request created by `entry-a-2` in Bank A.
- At least one request created by `entry-b` in Bank B.
- At least one request created by `entry-b-2` in Bank B.
- A high-value request and a normal-value request if SLA/risk indicators are enabled.

## Bank Visibility Baseline

Run this baseline early and repeat it after major workflow transitions:

| Actor | Must see | Must not see |
| --- | --- | --- |
| `entry-a` | Bank A requests created by `entry-a` and `entry-a-2` | Any Bank B request created by `entry-b` |
| `entry-a-2` | Bank A requests created by `entry-a` and `entry-a-2` | Any Bank B request created by `entry-b` |
| `entry-b` | Bank B requests created by `entry-b` and `entry-b-2` | Any Bank A request created by `entry-a` or `entry-a-2` |
| `entry-b-2` | Bank B requests created by `entry-b` and `entry-b-2` | Any Bank A request created by `entry-a` or `entry-a-2` |
| `reviewer-a` / `reviewer-a-2` | Bank A submitted/reviewable requests | Any Bank B request |
| `reviewer-b` / `reviewer-b-2` | Bank B submitted/reviewable requests | Any Bank A request |
| `bank-admin-a` | Bank A staff, merchants, requests, reports | Bank B staff, merchants, requests, reports |
| `bank-admin-b` | Bank B staff, merchants, requests, reports | Bank A staff, merchants, requests, reports |
| `swift-bank-a` | Bank A SWIFT-stage requests | Bank B SWIFT-stage requests |
| `swift-bank-b` | Bank B SWIFT-stage requests | Bank A SWIFT-stage requests |

CBY roles may have cross-bank oversight according to their role, but only authorized workflow actors may mutate workflow state.

## Canonical Workflow Spine

Run at least one happy-path request through this exact sequence:

1. `DATA_ENTRY`: create request in `DRAFT`.
2. `DATA_ENTRY`: upload request PDF.
3. `DATA_ENTRY`: submit request, expecting `SUBMITTED`.
4. `BANK_REVIEWER`: start bank review, expecting `BANK_REVIEW`.
5. `BANK_REVIEWER`: approve request, expecting `BANK_APPROVED` or `SUPPORT_REVIEW_PENDING`.
6. `SUPPORT_COMMITTEE`: claim the request, expecting `SUPPORT_REVIEW_IN_PROGRESS`.
7. `SUPPORT_COMMITTEE`: heartbeat while reviewing; second support user can view but cannot review.
8. `SUPPORT_COMMITTEE`: approve request, expecting `SUPPORT_APPROVED`, then handoff to SWIFT stage.
9. `SWIFT_OFFICER`: upload required SWIFT-stage PDFs, expecting `SWIFT_UPLOADED` or next configured voting-ready state.
10. `COMMITTEE_DIRECTOR`: open executive voting, expecting `EXECUTIVE_VOTING_OPEN`.
11. `EXECUTIVE_MEMBER`: submit vote once.
12. `COMMITTEE_DIRECTOR`: vote if applicable, close voting, expecting `EXECUTIVE_VOTING_CLOSED`.
13. `COMMITTEE_DIRECTOR`: finalize decision, expecting `EXECUTIVE_APPROVED` or `EXECUTIVE_REJECTED`.
14. `COMMITTEE_DIRECTOR`: generate and complete external FX confirmation, expecting `FX_CONFIRMATION_PENDING`, `CUSTOMS_DECLARATION_ISSUED`, or `COMPLETED` according to current implementation state.
15. All relevant roles: verify final visibility, document access, read-only state, notifications, history, and audit trail.

## Required Alternate Workflow Paths

Run these in addition to the happy path:

| Path | Expected result |
| --- | --- |
| Bank reviewer returns to Data Entry | Request becomes `BANK_RETURNED` or editable returned state; Data Entry can correct and resubmit. |
| Bank reviewer terminal rejection | Request becomes `BANK_REJECTED`; no role can reopen, edit, or resubmit. |
| Support committee returns/rejects | Bank Reviewer sees post-support rejection/return decision queue. |
| Bank reviewer returns support-rejected request to Data Entry | Request becomes an editable returned state for Data Entry. |
| Support claim conflict | Only one support reviewer can actively review; others see owner and cannot approve/reject. |
| Support claim timeout/release | Claim releases after manual release or timeout; queue returns to claimable state. |
| Executive rejection | Request becomes `EXECUTIVE_REJECTED`; immutable terminal state. |
| Vote auto-abstain | Closing voting records non-voting members as `AUTO_ABSTAIN_TIMEOUT`. |
| SWIFT incomplete upload | SWIFT submission is blocked until all required PDFs are uploaded. |

## Global Test Notes

- Start every role test by logging in from a clean browser session.
- Use `playwright-cli` for browser evidence when running automated or assisted verification.
- Capture screenshots for dashboard, request list, request detail, action dialog, final state, and audit/history evidence.
- Keep request numbers and actor aliases in the notes for traceability.
- For every action dialog, verify Arabic business copy, required comments, cancellation behavior, and final confirmation behavior.
- For every request state change, verify the originating role loses or gains only the appropriate next actions.
- For every upload, test valid PDF acceptance and non-PDF rejection.
- For every terminal state, attempt edit/resubmit/upload/vote actions from at least one forbidden role and confirm the action is absent in UI and rejected by backend if forced.
- Do not accept a test pass where a forbidden role sees a control but receives a backend error later; role-forbidden controls should not render.

## Evidence Template

Use this format in manual run notes:

```text
Run ID:
Date:
Environment:
Tester:
Browser:
Seed users:
Primary request number:
Alternate request numbers:

Step:
Actor:
Current status before:
Action:
Expected status after:
Actual status after:
Screenshots:
Audit/history evidence:
Pass/Fail:
Notes:
```

## Exit Criteria

The full system test is complete only when:

- The happy-path request reaches the final completed/external FX confirmation state.
- All alternate workflow paths above have been exercised.
- Each role file has been executed at least once.
- Cross-bank visibility checks pass for every bank-side role.
- CBY oversight does not mutate workflow state except through authorized roles.
- Audit and request history match the full workflow chain.
- No role-inappropriate workflow control is visible.
