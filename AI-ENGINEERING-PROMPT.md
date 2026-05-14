# Yemen Flow Hub — AI Engineering Master Prompt

---

## 1. System Overview

Yemen Flow Hub is a workflow-critical government banking platform designed to manage complex banking regulatory processes with uncompromising workflow integrity. This system is NOT a toy dashboard or a startup SaaS app. It is an enterprise-grade platform supporting strict role-based access control, immutable workflow transitions, and comprehensive audit logging. The platform is Arabic-first with full RTL support, built to serve government banking operations reliably and securely.

---

## 2. Product Purpose

The platform's primary purpose is to facilitate and enforce a rigorous, auditable, and immutable workflow for banking regulatory approvals and customs declarations. It ensures operational clarity, enforces strict role-based queues, and supports multi-stage approvals without compromise. The system must support government auditors, bank officers, support teams, and executives with clear, immutable states and transparent audit trails.

---

## 3. Engineering Philosophy

- Strict enforcement of workflow integrity: no direct status mutations.
- Opinionated architecture: service-oriented backend, queue-first frontend.
- Enterprise-grade security and auditability.
- Implementation-focused, avoiding visual clutter or startup SaaS aesthetics.
- Arabic RTL-first UI with calm, minimalistic Apple-inspired design.
- All business logic centralized in backend services; frontend is a thin, reactive client.
- Immutable workflow transitions after internal bank approval.
- All state changes are transactional and audited.
- The platform must NEVER behave like a shared admin dashboard.
- Every workflow view must be ownership-scoped and queue-scoped.
- Operational visibility is as important as workflow integrity.

---

## 4. Frontend Architecture

- Nuxt 4 with Vue 4 and TypeScript.
- State management with Pinia and VueUse.
- UI styled with Tailwind CSS and shadcn-vue components.
- Full RTL support with IBM Plex Sans Arabic font.
- Queue-first operational workspace emphasizing ownership-scoped queues and workflow-specific visibility.
- Minimal animations and no flashy SaaS dashboard elements.
- Dynamic queues, optimistic UI updates, timeline visualizations, and locked request states.
- Role-aware actions with read-only views for locked workflow stages.
- Support reviewer ownership clearly visualized.
- Dashboards must behave as operational workspaces, not shared analytics dashboards.
- Every request listing must be ownership-aware and queue-scoped.

---

## 5. Backend Architecture

- Laravel 11 with PHP 8.3+.
- Service-oriented architecture with dedicated services per domain.
- MySQL with proper indexing, foreign keys, and audit tables.
- Redis for queue workers and caching.
- Sanctum for authentication and authorization.
- WorkflowService as the single source of truth for all workflow state transitions.
- Policies, DTOs, Enums, Action classes, Form Requests, Queue jobs, Audit and Voting services.
- All workflow transitions are transactional and atomic.
- Scoped query architecture is mandatory.
- Ownership filtering must be enforced at database query level.
- APIs must never expose unrelated workflow items.

---

## 6. Workflow & Business Rules

All workflow rules, lifecycle stages, rejection logic, voting governance, and operational visibility rules must strictly follow:

- docs/01-workflow-and-business-rules.md

---

## 7. RBAC, Visibility & Authorization

All RBAC rules, operational visibility rules, queue visibility logic, and organizational access policies must strictly follow:

- docs/01-workflow-and-business-rules.md
- docs/05-backend-guide.md
- docs/06-api-reference.md

## 8. Workflow Locking Rules

All workflow locking behavior, immutable states, support review claiming rules, and request editability constraints must strictly follow:

- docs/01-workflow-and-business-rules.md
- docs/05-backend-guide.md

---

## 9. Support Review Claiming

Support review claiming behavior, locking logic, reviewer assignment, and release mechanisms must strictly follow:

- docs/01-workflow-and-business-rules.md
- docs/05-backend-guide.md

---

## 10. Voting System Rules

Executive voting lifecycle, voting session management, tie-breaking, executive director authority, and rejection finality must strictly follow:

- docs/01-workflow-and-business-rules.md
- docs/05-backend-guide.md
- docs/06-api-reference.md

---

## 11. Database Architecture

Database structure, entity relationships, audit metadata, ownership tracking, workflow history, and indexing strategy must strictly follow:

- docs/03-database-and-models.md

---

## 12. API Architecture

All API structures, response contracts, queue-scoped APIs, ownership filtering, and endpoint conventions must strictly follow:

- docs/06-api-reference.md

---

## 13. State Management Rules

Frontend state management, workflow synchronization, queue updates, and visibility state handling must strictly follow:

- docs/04-frontend-guide.md

---

## 14. Frontend Engineering Rules

Frontend architecture, RTL handling, operational dashboard philosophy, queue UX, and component behavior must strictly follow:

- docs/04-frontend-guide.md
- docs/02-system-architecture.md

---

## 15. Backend Engineering Rules

Backend service architecture, WorkflowService behavior, authorization enforcement, audit logging, and queue processing must strictly follow:

- docs/05-backend-guide.md
- docs/02-system-architecture.md

---

## 16. Component Architecture

Frontend component structure, operational widgets, request tables, queue components, and workflow UI composition must strictly follow:

- docs/04-frontend-guide.md

---

## 17. File & Folder Structure

System folder structure and project organization must strictly follow:

- docs/02-system-architecture.md

---

## 18. Queue & Workflow UX Rules

Queue behavior, role-specific dashboards, workflow visibility, and operational UX patterns must strictly follow:

- docs/04-frontend-guide.md
- docs/01-workflow-and-business-rules.md

---

## 19. Audit Logging Rules

Audit logging structure, audit timelines, immutable history rules, and workflow auditability must strictly follow:

- docs/03-database-and-models.md
- docs/05-backend-guide.md

---

## 20. Security Rules

Authentication, authorization, workflow protection, upload validation, and backend security enforcement must strictly follow:

- docs/05-backend-guide.md
- docs/02-system-architecture.md

---

## 21. Notifications Architecture

Notification behavior and in-app notification handling must strictly follow:

- docs/04-frontend-guide.md
- docs/05-backend-guide.md

---

## 22. File Upload Architecture

PDF upload validation, immutable file handling, SWIFT upload rules, and secure file management must strictly follow:

- docs/03-database-and-models.md
- docs/05-backend-guide.md

---

## 23. PDF & Customs Declaration Rules

Customs declaration generation, immutable declaration behavior, PDF workflows, and executive director permissions must strictly follow:

- docs/01-workflow-and-business-rules.md
- docs/05-backend-guide.md

---

## 24. Error Handling Standards

Frontend and backend error handling behavior must strictly follow:

- docs/04-frontend-guide.md
- docs/05-backend-guide.md

---

## 25. Coding Standards

All frontend and backend implementation standards must strictly follow:

- docs/04-frontend-guide.md
- docs/05-backend-guide.md

## Documentation Source of Truth

The following files are the authoritative source of truth for the project:

- docs/00-project-brief.md
- docs/01-workflow-and-business-rules.md
- docs/02-system-architecture.md
- docs/03-database-and-models.md
- docs/04-frontend-guide.md
- docs/05-backend-guide.md
- docs/06-api-reference.md
- docs/07-task-breakdown.md

The AI must always prioritize these documents over assumptions or generated patterns.

Avoid duplicating rules already defined in the documentation files.

---

## 26. AI Generation Constraints

- Do NOT generate fake or mock-only logic.
- Do NOT bypass WorkflowService for state mutations.
- Do NOT mutate workflow statuses directly.
- Do NOT duplicate RBAC logic.
- Do NOT place business logic inside frontend components.
- Do NOT use heavy or flashy UI libraries.
- Do NOT generate startup SaaS dashboard aesthetics.
- Do NOT generate generic admin templates.
- Do NOT generate shared visibility dashboards.
- Do NOT return unrelated requests in APIs.
- Do NOT generate global request listings for non-admin users.
- Do NOT contradict the linked docs directory specifications.
- Do NOT invent workflow rules outside the documented architecture.

---

## 27. Anti-Patterns (What NOT To Generate)

- Direct database mutations of workflow statuses.
- Business logic inside Vue components.
- Unsecured or unvalidated API endpoints.
- Duplicate permission checks in frontend and backend.
- Overly complex animations or visual clutter.
- Ignoring audit logging or immutable workflow rules.
- Non-transactional state changes.
- Multiple users claiming the same support review task simultaneously.
- Shared request visibility across unrelated users.
- Global request dashboards for operational users.
- Unscoped request APIs.
- Frontend-only visibility filtering.

---

## 28. Development Priorities

1. Establish strict RBAC and authentication.
2. Implement WorkflowService with immutable state transitions.
3. Develop backend services, policies, and audit logging.
4. Build queue-first frontend workspace with role-aware queues.
5. Integrate support review claiming and voting systems.
6. Implement file upload, PDF generation, and customs declaration.
7. Ensure full RTL support and Arabic-native UI.
8. Add notification system and error handling.
9. Conduct comprehensive testing and security audits.

---

## 29. Recommended Implementation Order

- Backend: Authentication → WorkflowService → Policies → Audit → Voting → File handling → Notifications.
- Frontend: Layouts → Queues → Workflow details → Claiming UI → Voting UI → File uploads → PDF previews → Notifications.
- Database: Schema migrations → Indexing → Audit/history tables → Foreign keys.
- Testing and validation throughout all stages.

---

## 30. Final Engineering Principles

- Controllers must stay thin; delegate to services.
- No business logic in Vue components.
- No direct workflow status mutations; all transitions via WorkflowService.
- No duplicated permission logic; enforce server-side RBAC.
- Audit logging is mandatory and immutable.
- Workflow transitions must be transactional and atomic.
- Claiming actions must be atomic and exclusive.
- Frontend permissions are never trusted.
- UI prioritizes operational clarity over decoration.
- Arabic RTL-first design is mandatory.
- Queue-first dashboard philosophy governs UX.
- Ownership-scoped operational visibility is mandatory.
- Request visibility must always be queue-scoped and role-scoped.
- The platform must never behave like a shared admin dashboard.

---

Generate a production-grade internal banking regulatory workflow platform that strictly follows the linked documentation architecture.

The platform must feel operationally realistic, governance-driven, workflow-oriented, and enterprise-grade.

Prioritize:

- Workflow integrity
- Operational clarity
- Arabic RTL enterprise UX
- Immutable auditability
- Queue-based operational workflows
- Maintainable service-oriented architecture

Avoid startup SaaS patterns, fake dashboards, visual gimmicks, or undocumented workflow assumptions.

---

# Bank-Owned Workflow Model

Import requests are owned by the bank entity, NOT by individual users.

All Data Entry users within the same bank can:

- Create drafts
- Continue incomplete drafts
- Edit returned requests
- Resubmit corrected requests

The workflow must NEVER depend on the availability of a specific employee.

Users are tracked as workflow actors for audit purposes only.

Workflow actor examples:

- Created By
- Last Updated By
- Submitted By
- Reviewed By
- Rejected By
- Resubmitted By

---

# Organizational Visibility Rules

## Bank Visibility

All users within the same bank can view all requests belonging to their bank.

However:

- Actions remain role-scoped
- Dashboards remain role-specific
- Workflow actions remain permission-controlled

This model supports:

- Operational continuity
- Team collaboration
- Employee replacement
- Workflow resilience

The platform is organization-owned, not user-owned.

---

# Soft Claiming Model

Support review claiming is temporary and presence-based.

A support reviewer temporarily locks the request only while actively reviewing it.

If the reviewer:

- Leaves the request page
- Disconnects
- Times out

The claim is automatically released.

Other support reviewers can then claim the request.

---

# Support Review Queue Rules

Support review queues behave as operational work queues.

Rules:

- Only one reviewer can actively review a request at a time
- Claims are temporary
- Claims are automatically released after 15 minutes of inactivity (TTL managed via Redis)
- The frontend must send a heartbeat ping every 60 seconds to extend the claim TTL
- Queue visibility remains available to all support users
- Support users can see who is actively reviewing a request

---

# Executive Voting Governance

Executive voting is controlled through a managed voting session.

## Executive Committee Director

The Executive Committee Director has authority to:

- Open voting sessions
- Close voting sessions
- Participate in voting
- Resolve tie decisions
- Finalize executive decisions
- Issue customs declarations

---

## Voting Lifecycle

### WAITING_FOR_VOTING_OPEN

The request is waiting for the Executive Committee Director to open voting.

### EXECUTIVE_VOTING_OPEN

Voting is currently active.

Executive members can:

- Approve
- Reject
- Abstain

### EXECUTIVE_VOTING_CLOSED

Voting has been closed by the Executive Committee Director.

Votes are calculated based on the current vote state.

---

## Voting Rules

- The Executive Committee Director also votes as a regular member
- In case of tie, the Director's vote becomes the deciding vote
- There is NO minimum quorum requirement
- The Director can close voting at any time

---

## Automatic Abstain Rule

When voting closes:

Any member who did not vote is automatically marked as:

- AUTO_ABSTAIN_TIMEOUT

This is different from a manual abstain vote.

The system must preserve this distinction in audit history.

---

# Final Executive Rejection

Executive rejection is FINAL.

Once a request is executive rejected:

- It cannot be reopened
- It cannot be edited
- It cannot be resubmitted
- It cannot be overridden by admins

This is a terminal workflow state.

---

# Customs Declaration Governance

Only the Executive Committee Director can issue customs declarations.

Once issued:

- The customs declaration becomes immutable
- The declaration cannot be revoked
- The declaration cannot be regenerated
- The declaration becomes a permanent legal workflow artifact

## PDF Generation Failure Handling

If the PDF generation process fails (e.g., rendering library error, storage write failure) before the declaration record is committed, the entire transaction must be rolled back. The declaration is only considered issued once:

1. The `customs_declarations` record is created in the database
2. The PDF is successfully written to storage
3. The `import_requests.customs_declaration_id` is updated

All three steps are wrapped in a single database transaction. If any step fails, no partial state is persisted and the Director can retry the issuance action.

---

# File Upload Rules

The MVP only supports PDF uploads.

Allowed uploads:

- Request documents
- SWIFT documents
- Generated customs declarations

Rules:

- Uploaded PDFs are immutable
- SWIFT documents cannot be replaced after upload
- Files are linked permanently to workflow history

---

# User Lifecycle Rules

Users are never hard-deleted.

When employees leave:

- Accounts should be archived or disabled
- Historical audit references must remain intact
- Workflow history must preserve all actor names permanently

---

# Password and Credential Security

User accounts must enforce:

- Minimum password length of 12 characters
- Passwords hashed using bcrypt (Laravel default)
- No password expiry enforced in MVP; configurable for future compliance requirements
- Password reset flow via email (out of MVP scope; accounts created by CBY Admin initially)
- Account lockout after 10 consecutive failed login attempts (15-minute lockout)

---

# Failed Authorization Audit Logging

Every failed authorization attempt must be recorded in `audit_logs`.

Examples of events that must be logged even on failure:

- Login with incorrect credentials (action: `AUTH_FAILED`)
- Accessing a request outside organization scope (action: `UNAUTHORIZED_ACCESS_ATTEMPT`)
- Attempting a workflow action without the required role (action: `UNAUTHORIZED_WORKFLOW_ACTION`)
- Attempting to mutate an immutable-state request (action: `IMMUTABLE_STATE_VIOLATION`)

Failed events should use `user_id: NULL` for unauthenticated attempts, or the authenticated user's ID for authenticated-but-unauthorized attempts.