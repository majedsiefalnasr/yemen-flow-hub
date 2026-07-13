# CBY_ADMIN — Central System Administrator

Arabic label: مسؤول النظام (CBY)

## Role Overview

The platform/system administrator for CBY. This role owns global visibility, entity management, CBY-side staff management, document rules, system settings, and cross-bank analytics. It is not the business owner of workflow actions — CBY_ADMIN cannot upload SWIFT documents, cast executive votes, claim support reviews, or finalize director decisions.

Full visibility does not mean unlimited action authority. The role is a platform administrator, not a default workflow approver.

Scope: all banks, all requests, all entities, all users (CBY-side).

---

## Sidebar Navigation

| Group                    | Item                                    | Route                |
| ------------------------ | --------------------------------------- | -------------------- |
| الرئيسية (Main)          | اللوحة الرئيسية (Dashboard)             | /dashboard           |
| الرئيسية                 | طلبات التمويل (Requests)                | /requests            |
| الرئيسية                 | الإشعارات (Notifications)               | /notifications       |
| الإدارة (Administration) | إدارة المستوردين (Importers)            | /merchants           |
| الإدارة                  | التقارير والتحليلات (Reports)           | /reports             |
| الإدارة                  | التدقيق والامتثال (Audit)               | /audit               |
| الإدارة                  | إدارة المستخدمين (CBY Staff Management) | /admin/cby-staff     |
| الإدارة                  | الكيانات (Entities / Banks)             | /admin/entities      |
| الإدارة                  | قواعد المستندات (Document Rules)        | /admin/workflow-docs |
| الإدارة                  | الصلاحيات (Permissions Reference)       | /admin/roles         |
| الأخرى (Other)           | الإعدادات (Settings)                    | /settings            |

The sidebar footer provides access to Profile.

---

## Pages

### Dashboard (`/dashboard`)

The CBY Admin dashboard is a strategic oversight and governance surface — not an operational work queue.

Its primary purpose is to help CBY administrators quickly answer:

- Is the platform operating normally?
- Where are workflow bottlenecks forming?
- Which banks or stages are becoming risky?
- Are executive decisions being delayed?
- Are there compliance or audit anomalies requiring investigation?

The dashboard should feel like a national operations/governance control center, not a task inbox.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "مسؤول النظام (CBY)"
- Read-only oversight badge: "إشراف فقط"
- No New Request button

A global toolbar appears below the header:

- Date range filter
- Bank filter dropdown
- Refresh button
- Last updated timestamp
- Export Executive Summary PDF button

All dashboard widgets respond to the active filters.

---

## Top KPI Row — System Health Snapshot

The first row contains 6 high-priority KPI cards focused on operational health and governance risk.

Each card includes:

- Main metric
- Delta/trend versus previous period
- Severity color
- Mini sparkline/trend indicator
- Click-through drilldown behavior

| KPI                            | Purpose                                                        | Severity Logic           |
| ------------------------------ | -------------------------------------------------------------- | ------------------------ |
| Active Workflow Requests       | Total currently active/non-terminal requests                   | Informational            |
| SLA Violations                 | Requests delayed beyond configured SLA                         | Warning/Error            |
| Open Executive Voting Sessions | Currently open executive voting sessions                       | Warning when aging       |
| FX Confirmation Pending        | Requests waiting for final external FX confirmation completion | Warning when delayed     |
| Bank Risk Alerts               | Banks with elevated operational/compliance risk signals        | Warning/Error            |
| System Availability            | Current platform uptime/health                                 | Error only when degraded |

KPI cards use semantic coloring only:

- Red = urgent/problematic
- Yellow = needs attention
- Green = healthy
- Blue/neutral = informational

Decorative-only color usage should be avoided.

---

## Workflow Pressure Map

This is one of the most important sections on the dashboard.

Instead of a simple status distribution chart, the dashboard shows workflow pressure and bottlenecks by stage.

**Display:**
A stage-pressure heatmap/table.

| Column       | Description                               |
| ------------ | ----------------------------------------- |
| Stage        | Workflow stage name                       |
| Active Count | Number of requests currently in stage     |
| Average Age  | Average time spent in stage               |
| SLA Risk     | Healthy / Warning / Critical              |
| Trend        | Increasing / Stable / Decreasing pressure |

The purpose of this section is to help administrators immediately identify where the workflow is slowing down.

Rows with SLA risk should use strong visual emphasis.

Clicking a row opens the Requests page pre-filtered to that stage.

---

## Executive Voting Oversight

A governance-focused section for monitoring executive voting activity.

This section reflects the updated workflow where:

- Executive voting opens automatically after support approval
- Voting cannot close until all active executive members vote

**Open Sessions panel:**
Displays:

- Request reference
- Bank
- Amount
- Session age
- Remaining members who have not voted yet
- Current voting completion state

The UI should explicitly show which members are still pending instead of only showing numeric quorum-style progress.

Example:

- Waiting for: Ahmed, Sara

High-value or aging executive sessions should receive elevated visual priority.

The CBY Admin cannot vote, close, or finalize sessions from this page.

---

## Bank Risk Intelligence

Cross-bank operational and compliance monitoring section.

**Display:**
A sortable risk/intelligence table.

| Column           | Description                                     |
| ---------------- | ----------------------------------------------- |
| Bank             | Bank name                                       |
| Request Volume   | Current active request load                     |
| Approval Rate    | Approval percentage                             |
| Average SLA Time | Average request processing duration             |
| Risk Score       | Composite operational/compliance risk indicator |
| Alerts           | Active issues or anomalies                      |

Future risk scoring may include:

- Rejection spikes
- Repeated support returns
- Long-running requests
- Unusual value patterns
- Reviewer inconsistency
- Executive delays

Clicking a bank opens bank-scoped oversight views.

---

## Compliance & Audit Signals

This section surfaces actionable governance and audit anomalies.

It is not a raw audit-log dump.

**Example insight cards:**

- Duplicate invoice usage detected
- Unusual login location
- Excessive rejection rate by reviewer
- Voting delays exceeding threshold
- FX confirmation delays
- Duplicate merchants across banks
- Abnormal workflow activity spikes

Each card links directly to the relevant filtered audit or requests view.

---

## Critical Events Feed

A compact chronological feed of high-importance governance/system events.

Examples:

- Voting finalized
- External FX confirmation completed
- CBY role assignment changed
- Security-sensitive login event
- Entity activated/deactivated
- Document-rule modification

This feed intentionally excludes low-value operational noise.

---

## Dashboard UX Principles

- The dashboard must prioritize actionable oversight over raw analytics.
- The dashboard must not behave like a task inbox.
- Requests tables should appear only when they represent actionable exceptions or risks.
- Large generic "Recent Requests" tables should be avoided on the main dashboard.
- Drilldown behavior should exist across all KPI cards and oversight widgets.
- Real-time indicators and refresh states should be visible.
- SLA severity visualization must be consistent across the platform.
- The visual tone should feel serious, calm, and operationally trustworthy.
- Avoid decorative charts that do not support operational decisions.

### Requests List (`/requests`)

This page is the national workflow registry and investigation surface for CBY oversight.

It is not a simple request table.

The primary goals of this page are:

- Monitor workflow pressure
- Investigate bottlenecks and delays
- Track executive voting activity
- Monitor FX confirmation completion
- Investigate anomalies and risk signals
- Drill into workflow history and audit context

CBY Admin access is read-only oversight access.

Scope: all banks.

---

## Page Header

**Title:**

- "طلبات تمويل الواردات"

**Subtitle:**

- "سجل وطني لمتابعة الطلبات، الاختناقات، ومؤشرات المخاطر التشغيلية"

No New Request button is shown.

---

## Smart Summary Bar

A high-priority intelligence strip appears directly below the header.

Examples:

- "42 طلباً تجاوز SLA"
- "3 جلسات تصويت متأخرة"
- "5 طلبات تنتظر FX Confirmation منذ أكثر من 24 ساعة"
- "بنك واحد لديه معدل رفض مرتفع بشكل غير طبيعي"

Each item is clickable and opens the table pre-filtered to the relevant context.

The summary bar focuses on operational exceptions and governance attention areas — not generic totals.

---

## Primary Tabs

Tabs are organized by operational meaning rather than low-level technical status groups.

| Tab              | Purpose                                                                 |
| ---------------- | ----------------------------------------------------------------------- |
| Active           | All active non-terminal requests                                        |
| Needs Attention  | SLA breaches, stalled requests, delayed voting, delayed FX confirmation |
| Executive Voting | Requests currently in executive voting lifecycle                        |
| FX Pending       | Requests waiting for SWIFT or FX confirmation completion                |
| Rejected         | All terminally rejected requests                                        |
| Completed        | Fully completed requests                                                |
| All Requests     | Full registry across all stages                                         |

Internal workflow statuses still exist and remain searchable/filterable.

---

## Toolbar

### Primary controls

- Search input:
  - Request reference
  - Merchant name
  - Invoice number
  - Bank name
- Export button
- Refresh button
- Saved Views dropdown
- Customize Columns dropdown

### Saved Views examples

- Delayed > 48h
- Open voting > 24h
- High-value pending
- FX delayed
- Support congestion
- High-risk requests

---

## Advanced Filters Drawer

A slide-over filter panel provides advanced investigation filters.

### Basic filters

- Bank
- Workflow stage
- Exact internal status
- Date range
- Amount range

### Advanced governance filters

- SLA breached only
- Delayed more than X hours
- High-risk requests only
- Executive voting active
- Waiting for specific executive member
- FX confirmation pending
- Multiple support returns
- High-value requests

The filter state should remain shareable/bookmarkable through URL query state.

---

## Requests Intelligence Table

This is an intelligence-oriented workflow table, not a generic CRUD grid.

### Core columns

| Column        | Purpose                                       |
| ------------- | --------------------------------------------- |
| Reference     | Unique request identifier                     |
| Bank          | Originating bank                              |
| Merchant      | Merchant name                                 |
| Amount        | Financial value                               |
| Current Stage | Human-readable workflow stage                 |
| Age in Stage  | Time spent in current stage                   |
| SLA State     | Healthy / Warning / Critical                  |
| Voting State  | Voting completion visibility where applicable |
| FX State      | SWIFT / FX confirmation progress              |
| Last Activity | Latest workflow action timestamp              |
| Risk Flags    | Operational/compliance indicators             |
| Actions       | View-only actions                             |

---

## Status Presentation

Statuses should use dual representation:

### Primary badge

Business-friendly wording.

Examples:

- Under Support Review
- Waiting for SWIFT
- Executive Voting
- FX Confirmation Pending

### Secondary metadata

Exact internal workflow status shown in muted/smaller text.

Example:

- `SUPPORT_REVIEW_IN_PROGRESS`

The page should avoid overwhelming users with raw enum-heavy presentation.

---

## Voting Visibility

For executive-stage requests, the table should display voting completion state.

Examples:

- `4/6 voted`
- `Waiting for: Ahmed, Sara`

The workflow reflects the current rule:

- Voting opens automatically after support approval
- Voting cannot close until all active executive members vote

High-value or aging voting sessions should receive elevated visual severity.

---

## Age & SLA Visualization

Time pressure is one of the most important oversight indicators.

`Age in Stage` should use strong visual hierarchy:

- Hours/days visible at a glance
- Consistent SLA color system
- Escalating severity styling

Examples:

- Green: healthy
- Yellow: approaching SLA threshold
- Red: SLA breach

---

## Risk & Escalation Indicators

Requests may display escalation badges such as:

- Delayed executive vote
- Repeated support returns
- High-risk bank
- Long-running request
- Duplicate invoice detected
- FX completion delayed

These indicators should be visually compact but highly noticeable.

---

## Actions Column

CBY Admin is read-only from a workflow perspective.

Allowed actions:

- View Request
- Open Timeline
- Open Audit View

Not allowed:

- Approve
- Reject
- Vote
- Claim
- Upload SWIFT
- Upload FX confirmation
- Reassign ownership
- Edit request data

---

## Row Interaction Behavior

Rows should support:

- Full-row click behavior
- Quick-preview drawer
- Optional full-page navigation

The goal is rapid investigation without excessive navigation churn.

---

## Request Preview Drawer

A right-side preview drawer should allow rapid inspection.

Suggested content:

- Workflow timeline
- Current blockers
- Latest documents
- Voting state
- FX progress
- Audit summary
- Risk indicators

The drawer is read-only.

---

## Table UX Principles

- Prioritize investigation speed over dense data dumping
- Avoid turning the page into a raw audit log
- Preserve high information density while remaining scannable
- Support power-user workflows:
  - column pinning
  - density modes
  - keyboard navigation
  - saved views
- Prefer operational intelligence over generic reporting
- Use real-time updates where practical

### Request Detail (`/requests/[id]`)

The CBY Admin request detail page is a read-only investigation and oversight surface.

It should help the admin answer:

- What is the current state of this request?
- Where is it blocked?
- Who is expected to act next?
- Has the request breached SLA?
- Are there voting, document, FX, or audit concerns?

CBY Admin must not receive workflow action controls on this page.

---

## Page Header

The header should summarize the request at a glance.

Recommended elements:

- Request reference number
- Bank name
- Merchant name
- Amount and currency
- Current business-facing status badge
- Exact internal workflow status as secondary metadata
- Age in current stage
- SLA state
- Risk flags
- Read-only oversight badge: "إشراف فقط"

Primary actions:

- Export Case File
- Open Audit View
- Copy Request Link

Forbidden actions:

- Approve
- Reject
- Claim
- Vote
- Upload SWIFT
- Upload FX confirmation
- Edit request data

---

## Current Blocker Panel

This is the most important intelligence component on the detail page.

It explains what is preventing the request from moving forward.

Examples:

- Waiting for support committee claim
- Under support review by [name]
- Waiting for executive members to vote
- Waiting for SWIFT and FX request upload by bank
- Waiting for signed/stamped external FX confirmation upload by Committee Director
- Completed / no blocker
- Rejected / terminal state

The panel should show:

- Current owner role
- Current responsible actor if known
- Required next action
- Time spent waiting
- SLA risk

CBY Admin sees this information only for oversight; no action button is shown.

---

## Workflow Progress

Show a horizontal progress component using the updated workflow sequence:

`DRAFT`
→ `SUBMITTED`
→ `BANK_REVIEW`
→ `BANK_APPROVED`
→ `SUPPORT_REVIEW_PENDING`
→ `SUPPORT_REVIEW_IN_PROGRESS`
→ `SUPPORT_APPROVED`
→ `EXECUTIVE_VOTING_OPEN`
→ `EXECUTIVE_VOTING_CLOSED`
→ `EXECUTIVE_APPROVED`
→ `WAITING_FOR_SWIFT`
→ `SWIFT_UPLOADED`
→ `FX_CONFIRMATION_PENDING`
→ `COMPLETED`

Rejected terminal states should branch visually:

- `BANK_REJECTED`
- `SUPPORT_REJECTED`
- `EXECUTIVE_REJECTED`

Returned states should be shown as loops:

- `BANK_RETURNED`
- `SUPPORT_RETURNED`

---

## Tabs

Use investigation-oriented tabs rather than simple form-only tabs.

| Tab               | Purpose                                                     |
| ----------------- | ----------------------------------------------------------- |
| Overview          | Request summary, financial data, merchant/import details    |
| Workflow Timeline | Full lifecycle history and status transitions               |
| Documents         | All permitted documents and document history                |
| Executive Voting  | Voting state, votes, pending members, final decision        |
| FX Confirmation   | SWIFT, FX request, and final external FX confirmation state |
| Parties           | Actors and institutions involved across the lifecycle       |
| Audit Trail       | Security and workflow audit events related to the request   |

---

## Overview Tab

Shows the main business data in grouped read-only sections:

- Request summary
- Bank and branch information
- Merchant information
- Invoice and import data
- Goods/category information
- Amount, currency, and financial fields
- Current status and SLA metadata

No editable fields are shown.

---

## Workflow Timeline Tab

Shows a chronological workflow timeline.

Each event should include:

- Status transition
- Actor name and role
- Timestamp
- Comment/reason if provided
- Document references if the transition included uploads
- System-generated markers for automatic transitions

Automatic transitions should be clearly labeled as system actions.

Examples:

- Support approval automatically opened executive voting
- Executive approval moved request to waiting for SWIFT
- SWIFT upload moved request to FX confirmation pending

---

## Documents Tab

CBY Admin can view/download permitted documents but cannot upload or replace documents.

Document groups:

- Request documents
- Bank review/support documents if applicable
- SWIFT document
- FX confirmation request document
- Generated external FX confirmation PDF
- Signed/stamped external FX confirmation PDF, if completed

Each document row should show:

- Document type
- File name
- Uploaded/generated by
- Upload/generation timestamp
- Current validity/state
- Download action if permitted

No upload button is shown for CBY Admin.

---

## Executive Voting Tab

Visible when the request has reached executive voting or a later stage.

This tab should show:

- Voting session status
- Session opened timestamp
- Completion state, e.g. `4/6 voted`
- Members who have voted
- Members still pending
- Vote value where visibility policy permits
- Vote comments/reasons where visibility policy permits
- Final voting outcome after finalization

Important rule:

- Voting opens automatically after support approval
- Voting cannot close until all active executive members vote

CBY Admin cannot cast votes, close sessions, or finalize decisions.

---

## FX Confirmation Tab

Visible when the request has reached executive approval or later.

This tab tracks the post-executive-approval financial confirmation path.

State model:

- Waiting for SWIFT
- SWIFT uploaded
- FX request uploaded
- FX confirmation pending
- Completed

The tab should show:

- SWIFT document status
- FX confirmation request document status
- Generated external FX confirmation PDF status
- Signed/stamped external FX confirmation upload status
- Current responsible role
- Time waiting in current FX step

CBY Admin can download permitted documents but cannot upload or finalize the FX confirmation.

---

## Parties Tab

Shows all actors and institutions involved:

- Bank entity
- Data Entry user
- Bank Reviewer
- Support Committee claimant/reviewer
- Executive members
- Committee Director
- CBY Admin/system viewers where audit-relevant

For each actor, show:

- Name
- Role
- Organization/bank
- Relevant action(s)
- Timestamp(s)

---

## Audit Trail Tab

Shows request-scoped audit events only.

This should not replace the global Audit page.

Recommended filters:

- Workflow actions
- Document access
- Permission denials
- Security-sensitive events
- Status transitions

Each row should show:

- Event type
- Actor
- Role
- Timestamp
- IP/device metadata where available
- Related object/action

---

## Right-side Intelligence Panel

A persistent right-side panel summarizes oversight signals.

Suggested cards:

- Current owner role
- Current blocker
- SLA state
- Age in current stage
- Pending actors
- Latest activity
- Risk/anomaly flags
- Linked audit events

This panel should be compact and scannable.

---

## UX Principles

- Make current blocker and next expected action immediately visible.
- Keep all workflow controls hidden for CBY Admin.
- Prefer investigation clarity over form-style layout.
- Separate business data, workflow history, documents, voting, FX, and audit concerns into clear tabs.
- Use read-only visual treatment consistently.
- Surface automatic system transitions clearly.
- Make document provenance and upload/generation source obvious.

### Merchants (`/merchants`)

The CBY Admin merchants page is a cross-bank merchant registry and risk-oversight surface.

It should not behave like a simple CRUD table.

Its primary purpose is to help CBY Admin answer:

- Are the same merchants appearing across multiple banks?
- Are there possible duplicate merchant records?
- Are any merchants associated with repeated rejections or returns?
- Are merchant records incomplete or suspicious?
- Which merchants create the largest operational exposure?

CBY Admin has global merchant visibility across all banks. Merchant mutation actions, if allowed, should be treated as administrative exceptions and be audit-heavy.

---

## Page Header

**Title:**

- "سجل المستوردين"

**Subtitle:**

- "سجل رقابي للمستوردين عبر البنوك، التكرارات، ومؤشرات المخاطر"

Primary actions should not over-emphasize merchant creation.

If CBY Admin can create merchants, show `Add Merchant` as a secondary administrative action, not as the main page CTA.

---

## Smart Summary Bar

A compact intelligence strip highlights merchant governance issues.

Examples:

- Possible duplicate merchants
- Merchants active across multiple banks
- Merchants with repeated rejection history
- Merchants with missing registry/tax data
- High-value merchants by total request amount

Each summary item opens a pre-filtered merchant list.

---

## Primary Tabs

| Tab           | Purpose                                                |
| ------------- | ------------------------------------------------------ |
| All Merchants | Full cross-bank merchant registry                      |
| Duplicates    | Possible duplicate merchant records                    |
| High Risk     | Merchants with elevated risk signals                   |
| Missing Data  | Merchants with incomplete required fields              |
| High Activity | Merchants with high request volume or high total value |
| Inactive      | Inactive or suspended merchants                        |

---

## Toolbar

Controls:

- Search by merchant name, registry number, tax ID, or bank
- Bank filter
- Status filter
- Risk filter
- Export button
- Refresh button
- Customize Columns dropdown

---

## Merchants Intelligence Table

| Column          | Purpose                                                                     |
| --------------- | --------------------------------------------------------------------------- |
| Merchant        | Merchant name plus registry identifier                                      |
| Banks           | Banks associated with the merchant                                          |
| Tax ID          | Tax identifier                                                              |
| Active Requests | Current active requests                                                     |
| Total Requests  | Historical request volume                                                   |
| Total Amount    | Total value of related requests                                             |
| Rejection Rate  | Rejection percentage across requests                                        |
| Return Rate     | Return/correction frequency                                                 |
| Duplicate Risk  | Possible duplicate confidence/severity                                      |
| Last Activity   | Latest linked request or merchant update                                    |
| Status          | Active / Inactive / Suspended                                               |
| Actions         | View profile, open requests, open audit; limited admin actions if permitted |

The table should prioritize merchant risk and operational exposure, not just record management.

---

## Duplicate Risk Presentation

Possible duplicates should show compact but clear evidence.

Examples:

- Same tax ID, different names
- Same registry number across multiple banks
- Similar name + same phone/address
- Same merchant attached to repeated rejected requests

Duplicate indicators should link to a comparison view or profile drawer.

---

## Merchant Profile Drawer / Detail

Opening a merchant should show a read-heavy oversight profile.

Suggested sections:

- Basic merchant information
- Associated banks
- Active and historical requests
- Rejection/return history
- Duplicate candidates
- Document/completeness summary
- Risk signals
- Audit history

The drawer/page should support fast investigation without forcing immediate navigation away from the merchant list.

---

## Actions and Boundaries

Allowed oversight actions:

- View merchant profile
- Open linked requests
- Open merchant audit trail
- Export merchant record

Administrative mutation actions, if enabled:

- Edit merchant metadata
- Activate/deactivate merchant
- Merge/flag duplicate records, if supported by backend policy

Mutation actions should be clearly separated from normal oversight actions and require audit logging.

CBY Admin should not use merchant management as a substitute for bank-side operational data entry.

---

## UX Principles

- Treat merchants as cross-bank risk entities, not just dropdown options.
- Highlight duplicates and incomplete records early.
- Make bank associations visible without opening the record.
- Make request/rejection history easy to inspect.
- Use clear warning states for suspicious or incomplete merchant records.
- Avoid making `Add Merchant` the dominant action for CBY Admin.

---

### Reports (`/reports`)

The CBY Admin reports page is a historical analysis and executive reporting surface.

It is intentionally different from the dashboard:

- Dashboard = live oversight and operational monitoring
- Reports = historical analysis, comparison, export, and management review

The reports page should help CBY Admin answer:

- How is the platform performing over time?
- Which banks are improving or declining?
- Where are workflow bottlenecks concentrated?
- What are the approval, rejection, and return patterns?
- Are executive voting sessions becoming a bottleneck?
- How long does SWIFT and FX confirmation processing take after executive approval?
- Which operational or compliance risks are increasing?

Scope: all banks.

---

## Page Header

**Title:**

- "التقارير والتحليلات"

**Subtitle:**

- "تحليل تاريخي لأداء المنصة، البنوك، المراحل، التصويت، ومؤشرات المخاطر"

Primary actions:

- Export PDF
- Export Excel/CSV
- Schedule Report

---

## Global Report Filters

Filters affect all report tabs.

Recommended filters:

- Date range
- Bank
- Workflow stage
- Goods category
- Currency
- Amount range
- Risk level
- Request outcome

The active filter state should be clearly visible and easy to reset.

---

## Report Tabs

| Tab                     | Purpose                                          |
| ----------------------- | ------------------------------------------------ |
| Executive Summary       | High-level platform health and trends            |
| Bank Performance        | Compare banks by volume, SLA, outcomes, and risk |
| Workflow SLA            | Stage-level processing-time analysis             |
| Decisions & Outcomes    | Approval, rejection, and return analysis         |
| Executive Voting        | Voting duration and participation analysis       |
| SWIFT & FX Confirmation | Post-approval financial-confirmation analysis    |
| Compliance & Risk       | Risk indicators and audit-linked patterns        |

---

## Executive Summary Tab

High-level management summary suitable for export.

Recommended KPI cards:

- Total submitted requests
- Active requests
- Completed requests
- Rejected requests
- Average end-to-end processing time
- SLA breach rate
- Total request value by currency
- Active banks during selected period

Recommended charts:

- Monthly request volume trend
- Completed vs rejected trend
- Average processing-time trend
- Request value trend by currency

---

## Bank Performance Tab

Cross-bank operational comparison.

Suggested table columns:

| Column                  | Purpose                                 |
| ----------------------- | --------------------------------------- |
| Bank                    | Bank name                               |
| Submitted Requests      | Volume                                  |
| Completed Requests      | Successful outcomes                     |
| Rejected Requests       | Terminal failures                       |
| Support Returns         | Correction-quality indicator            |
| Approval Rate           | Acceptance rate                         |
| Average Processing Time | Efficiency                              |
| SLA Breach Rate         | Delay risk                              |
| FX Delay Rate           | Post-approval bottleneck indicator      |
| Risk Score              | Composite operational/compliance signal |

Suggested visualizations:

- Bank ranking by request volume
- Bank ranking by SLA breach rate
- Approval vs rejection comparison
- Support-return heatmap by bank

Clicking a bank should open a bank-filtered report or request registry.

---

## Workflow SLA Tab

Focused on processing-time and bottleneck analysis.

Stages should reflect the updated workflow:

- Draft / Submitted / Bank Review
- Support Review Pending / In Progress
- Executive Voting Open / Closed
- Waiting for SWIFT
- SWIFT Uploaded / FX Confirmation Pending
- Completed

Suggested metrics:

- Average time per stage
- Median time per stage
- P95 stage duration
- SLA breach count
- SLA breach rate
- Aging requests still inside stage

Suggested visualizations:

- Stage-duration bar chart
- SLA-breach heatmap
- Aging distribution by stage

The purpose is to identify where the workflow slows down in practice.

---

## Decisions & Outcomes Tab

Analyzes workflow and business outcomes.

Metrics:

- Bank approvals
- Bank returns
- Bank rejections
- Support approvals
- Support returns
- Support rejections
- Executive approvals
- Executive rejections
- Completed requests

Suggested charts:

- Outcome funnel
- Rejection/return trend over time
- Rejection reasons distribution
- Return reasons distribution
- Outcome by bank
- Outcome by goods category

This tab should help identify operational quality problems.

---

## Executive Voting Tab

Analyzes executive voting behavior and delays.

Current workflow rules:

- Voting opens automatically after support approval
- Voting cannot close until all active executive members vote
- No auto-abstain timeout behavior exists

Suggested metrics:

- Voting sessions opened
- Sessions finalized
- Average voting duration
- Longest open sessions
- Sessions delayed due to pending members
- Average time-to-vote by member
- Approval vs rejection outcomes

Suggested tables:

- Slowest voting sessions
- Members with highest average time-to-vote
- High-value requests delayed in voting

This tab is analytical only. CBY Admin cannot vote, close sessions, or finalize decisions.

---

## SWIFT & FX Confirmation Tab

Analyzes the post-executive-approval financial-confirmation flow.

Current workflow:

- Executive approval moves request to `WAITING_FOR_SWIFT`
- SWIFT Officer uploads:
  - SWIFT PDF
  - FX confirmation request PDF
- Request then moves to `FX_CONFIRMATION_PENDING`
- Committee Director downloads the generated external FX confirmation PDF, signs/stamps it externally, and uploads the signed version to complete the workflow

Suggested metrics:

- Requests waiting for SWIFT
- Average time from executive approval to SWIFT upload
- Average time from SWIFT upload to FX confirmation completion
- FX confirmation pending > SLA
- Completed FX confirmations in selected period

Suggested visualizations:

- Post-approval processing-time trend
- FX pending aging buckets
- Bank comparison by SWIFT upload delay
- Director completion delay trend

---

## Compliance & Risk Tab

Historical risk and anomaly analysis.

Suggested report cards:

- Duplicate invoice patterns
- Duplicate merchant patterns
- High-value requests by bank
- Unusual rejection spikes
- Permission-denial trends
- Suspicious access/login patterns
- Audit-sensitive event trends

Each insight should link to filtered Audit or Requests views.

---

## Scheduled Reports

CBY Admin may schedule recurring reports.

Suggested report types:

- Weekly executive summary
- Monthly bank-performance report
- Monthly SLA and bottleneck report
- Executive voting delay report
- FX confirmation delay report
- Compliance and audit anomalies report

Schedule options:

- Daily
- Weekly
- Monthly
- Custom range

Delivery options:

- Download only
- Email delivery if supported

---

## Export Behavior

Exports must respect active filters.

Supported exports:

- PDF summary
- Excel workbook
- CSV raw data

Excel exports should support multiple sheets where appropriate:

- Summary
- Bank Performance
- SLA Analysis
- Voting Analysis
- FX Analysis
- Risk Indicators

---

## UX Principles

- Keep reports analytical, not operational.
- Avoid duplicating the live dashboard.
- Make comparison easy: bank vs bank, stage vs stage, period vs period.
- Show trends and distributions, not just totals.
- Allow drilldown into Requests or Audit views.
- Keep export quality high because these reports support management and governance review.
- Use updated workflow terminology and avoid outdated customs terminology.

---

### Audit (`/audit`)

The CBY Admin audit page is a security, compliance, and investigation center.

It should not behave like a raw log dump.

The page should help CBY Admin answer:

- Who did what, when, and from where?
- Which sensitive workflow actions occurred?
- Were there permission denials or suspicious access attempts?
- Are there unusual user, bank, document, or voting behaviors?
- What evidence is available for compliance review or incident investigation?

Scope: all users, all banks, all requests, and all security-relevant platform activity.

---

## Page Header

**Title:**

- "التدقيق والامتثال"

**Subtitle:**

- "مركز تحقيق لمراجعة الأحداث الحساسة، الوصول، الصلاحيات، والتغييرات النظامية"

Primary actions:

- Export Audit Report
- Save Investigation View
- Refresh

No workflow action buttons are shown.

---

## Audit Event Categories

Events should be grouped into meaningful categories rather than presented as one flat stream.

| Category          | Examples                                                                      |
| ----------------- | ----------------------------------------------------------------------------- |
| Workflow Events   | submit, approve, return, reject, claim, finalize, complete FX confirmation    |
| Voting Events     | vote cast, vote changed, voting closed, decision finalized                    |
| Document Events   | document uploaded, downloaded, generated, replaced, denied                    |
| Access & Security | login, logout, failed login, suspicious login, MFA events                     |
| Permission Events | forbidden action, route denied, document denied, role mismatch                |
| Admin Changes     | user created, role changed, bank activated/deactivated, document rule changed |
| System Events     | automatic transition, scheduled job, system notification, integration event   |

---

## Smart Summary Bar

A compact risk summary appears above the event list.

Examples:

- Permission denials today
- Suspicious login attempts
- Sensitive role changes
- High-risk document downloads
- Failed workflow-action attempts
- Unusual voting delays or edits

Each item filters the audit stream to the matching context.

---

## Primary Tabs

| Tab           | Purpose                                                |
| ------------- | ------------------------------------------------------ |
| All Events    | Full audit stream                                      |
| Security      | Login, MFA, suspicious access, session/device activity |
| Workflow      | Request lifecycle and workflow decisions               |
| Documents     | Upload/download/generation/denial events               |
| Permissions   | Forbidden actions and role/surface denials             |
| Admin Changes | Users, roles, banks/entities, document rules, settings |
| Anomalies     | Grouped suspicious or unusual patterns                 |

---

## Filters

Recommended filters:

- Date/time range
- Actor/user
- Role
- Bank/entity
- Request reference
- Merchant
- Event category
- Event type
- Severity
- IP address
- Device/session
- Outcome: success / failed / denied / system

Filters should be URL-shareable to support investigation handoff.

---

## Audit Events Table

Core columns:

| Column      | Purpose                                         |
| ----------- | ----------------------------------------------- |
| Timestamp   | Exact event time                                |
| Severity    | Info / Warning / Critical                       |
| Event Type  | Human-readable event name                       |
| Actor       | User who performed or attempted the action      |
| Role        | Actor role at time of event                     |
| Bank/Entity | Actor or affected entity                        |
| Target      | Request, document, user, bank, setting, or rule |
| Outcome     | Success / Failed / Denied / System              |
| IP / Device | Investigation metadata where available          |
| Actions     | View details, open related object               |

Rows should be readable and compact, with severity emphasized clearly.

---

## Event Detail Drawer

Opening an audit event should show a right-side detail drawer.

Suggested content:

- Full event metadata
- Actor identity and role at event time
- Target object details
- Before/after values for admin changes
- Request/document references
- IP address, device, session, and user agent where available
- Related events in same session/request
- Copy event ID
- Export evidence snippet

This drawer is read-only.

---

## Anomaly Detection / Grouping

The page should support grouping related suspicious events into investigation-friendly patterns.

Examples:

- Multiple permission denials by the same user
- Repeated failed logins
- Unusual document-download volume
- Role change followed by sensitive action
- Executive voting delay pattern
- Repeated access attempts to other banks' data
- Duplicate merchant or invoice investigation triggers

Anomaly groups should link to the related events and affected objects.

---

## Relationship to Request Timeline

The Audit page is broader than a request workflow timeline.

Request Timeline:

- Focuses on a single request lifecycle
- Shows business/workflow progression

Audit:

- Covers system-wide security and compliance activity
- Includes denied attempts, access, admin changes, document access, and system events

Where useful, audit events should deep-link to request details, request timelines, documents, users, banks, or settings.

---

## Severity Model

Suggested severity levels:

| Severity | Meaning                                             |
| -------- | --------------------------------------------------- |
| Info     | Normal expected activity                            |
| Warning  | Unusual or attention-worthy event                   |
| Critical | Security, compliance, or governance-sensitive event |

Critical examples:

- CBY role assignment changed
- Permission bypass attempt
- Suspicious login
- Sensitive document access anomaly
- System configuration changed
- Workflow action denied on restricted state

---

## Export / Evidence Behavior

Audit exports should be investigation-ready.

Supported exports:

- Filtered CSV
- PDF investigation report
- Single-event evidence snippet

Exports should include:

- Active filters
- Timestamp range
- Actor and target details
- Event IDs
- Generated-by user
- Export timestamp

---

## UX Principles

- Prioritize investigation and compliance clarity over raw volume.
- Make severity and denied actions immediately visible.
- Group related events where possible.
- Preserve audit immutability: no edit/delete actions.
- Clearly distinguish system-generated actions from human actions.
- Avoid using the Audit page as a replacement for Reports or Request Timeline.
- Support fast drilldown to related requests, users, banks, documents, and settings.

---

### CBY Staff Management (`/admin/cby-staff`)

The CBY Staff Management page is an identity and access management surface for CBY-side users.

It should not behave like a basic employee CRUD table.

The page should help CBY Admin answer:

- Who currently has access to CBY-side workflow powers?
- Are critical roles covered by active, secure accounts?
- Are MFA and session security healthy?
- Were there recent sensitive role changes?
- Are there users with risky access patterns?
- Are role constraints and separation rules being respected?

Scope: CBY-side users only.

Allowed CBY roles:

- `SUPPORT_COMMITTEE`
- `EXECUTIVE_MEMBER`
- `COMMITTEE_DIRECTOR`
- `CBY_ADMIN`

Bank-side roles must not be assignable from this page.

---

## Page Header

**Title:**

- "إدارة مستخدمي CBY"

**Subtitle:**

- "إدارة الهوية، الأدوار، الجلسات، وأمان الوصول لموظفي البنك المركزي"

Primary actions:

- Add CBY User
- Export Users
- Open Role Audit

---

## Access Health Summary

A compact KPI row appears above the user table.

Suggested cards:

- Active CBY users
- MFA enabled percentage
- Suspended/inactive accounts
- Users with critical roles
- Recent role changes
- Active sessions
- Permission-denial alerts

These cards should be clickable and filter the user list or open audit views.

---

## Primary Tabs

| Tab                  | Purpose                                                                     |
| -------------------- | --------------------------------------------------------------------------- |
| All CBY Users        | Full CBY-side user list                                                     |
| Support Committee    | Support-review users                                                        |
| Executive Committee  | Executive members and Committee Director                                    |
| Administration       | CBY Admin users                                                             |
| Suspended / Inactive | Disabled or suspended accounts                                              |
| Security Review      | Users with MFA gaps, denied actions, suspicious activity, or stale sessions |

---

## Role Constraints and Guardrails

The page must clearly enforce and explain critical role constraints.

Rules:

- `COMMITTEE_DIRECTOR` is distinct from `EXECUTIVE_MEMBER`.
- The same user should not hold both `COMMITTEE_DIRECTOR` and `EXECUTIVE_MEMBER` at the same time.
- Bank-side roles must not be assignable to CBY users from this page.
- Deactivating users with active workflow responsibilities should require explicit handling.
- Removing or deactivating the last active `COMMITTEE_DIRECTOR` should be blocked.
- Disabling all active executive voters should be blocked or strongly prevented.

Constraint failures should show clear inline explanations, not generic validation errors.

Example:

- "لا يمكن تعيين هذا المستخدم كعضو تنفيذي لأنه مدير اللجنة التنفيذية بالفعل."

---

## Search and Filters

Recommended controls:

- Search by name or email
- Role filter
- Status filter
- MFA status filter
- Active session filter
- Last login range
- Risk/security flag filter

Filter state should be shareable through URL query parameters where practical.

---

## CBY Users Table

Suggested columns:

| Column           | Purpose                                          |
| ---------------- | ------------------------------------------------ |
| User             | Avatar, full name, email                         |
| Role             | CBY role badge                                   |
| Status           | Active / Inactive / Suspended                    |
| MFA              | Enabled / Missing / Required                     |
| Last Login       | Last successful login timestamp                  |
| Active Sessions  | Current active session count                     |
| Recent Denials   | Recent forbidden/denied actions                  |
| Workload Context | Role-specific workload summary                   |
| Risk Flags       | Security or governance warnings                  |
| Actions          | View profile, edit, suspend, reset access, audit |

`Created At` can exist in the profile drawer, but should not be more prominent than security and access-health columns.

---

## Role-specific Workload Context

The table should surface lightweight role-aware context.

Examples:

- `SUPPORT_COMMITTEE`: claimed requests, active support workload, average review age
- `EXECUTIVE_MEMBER`: pending votes, average time-to-vote, voting participation
- `COMMITTEE_DIRECTOR`: sessions waiting for close/finalization, FX confirmations pending/completed
- `CBY_ADMIN`: recent admin changes, role/document-rule changes

This context helps avoid disabling or editing a user without understanding operational impact.

---

## User Profile Drawer / Detail

Opening a user should show an IAM-focused profile.

Suggested sections:

- Identity details
- Current role and status
- MFA and password/access state
- Active sessions
- Recent audit events
- Recent permission denials
- Role-specific workload and pending responsibilities
- Role-change history
- Login history

The drawer should support quick investigation without leaving the list.

---

## Add / Edit User Flow

Fields:

- Full name
- Email
- CBY role
- Status
- MFA requirement
- Temporary password / invite flow, depending on implementation

Role selection must only include CBY roles.

When assigning critical roles, show role-impact guidance.

Examples:

- `COMMITTEE_DIRECTOR`: can close/finalize voting and complete external FX confirmation workflow
- `EXECUTIVE_MEMBER`: can cast executive votes only
- `SUPPORT_COMMITTEE`: can claim and decide support reviews
- `CBY_ADMIN`: can administer platform settings and users, but does not gain workflow actor powers by default

---

## Sensitive Actions

Sensitive actions should be separated visually from normal edit actions.

Possible actions:

- Edit user
- Suspend / reactivate user
- Reset password
- Require MFA re-enrollment
- Force logout all sessions
- Lock account temporarily
- View audit history

Sensitive actions require clear confirmation and mandatory reason where appropriate.

All sensitive actions must be audit logged.

---

## Deactivation / Suspension Rules

Before deactivation or suspension, the system should check for operational impact.

Examples:

- Active support claims
- Pending executive vote
- Open sessions where the user is required to vote
- Director-only pending finalization or FX confirmation work
- Last active user in a critical role

The UI should either block the action or require explicit handoff/confirmation depending on backend policy.

---

## Bulk Actions

Allowed bulk actions should be conservative.

Recommended:

- Export selected users
- Require MFA for selected users
- Force logout selected users, if supported

Avoid dangerous bulk actions such as mass role reassignment or mass suspension unless backend governance supports it.

---

## UX Principles

- Treat this page as security/IAM, not HR management.
- Make role coverage and access risk visible immediately.
- Make MFA/session/security indicators more prominent than creation date.
- Enforce role constraints with clear explanations.
- Make sensitive actions visibly distinct and audit-heavy.
- Prevent accidental disruption of active workflow responsibilities.
- Never allow bank-side role assignment from this CBY staff page.

---

### Entities / Banks (`/admin/entities`)

The Entities / Banks page is an institutional oversight and operational-health surface.

It should not behave like a simple CRUD registry of bank records.

The primary purpose of this page is to help CBY Admin answer:

- Which banks are operationally healthy?
- Which banks are missing critical workflow roles?
- Which banks are creating workflow bottlenecks?
- Which banks have elevated SLA or rejection risk?
- Which banks are operationally inactive or degraded?
- Which banks are delaying SWIFT or FX-confirmation steps?
- Which banks require governance or operational intervention?

Banks should be treated as operational workflow entities, not static records.

---

## Page Header

**Title:**

- "البنوك والكيانات"

**Subtitle:**

- "متابعة تشغيلية ورقابية للبنوك والكيانات المرتبطة بالمنصة"

Primary actions:

- Add Entity
- Export Entity Report
- Open Risk View

---

## Smart Summary Bar

A high-priority operational summary strip appears below the page header.

Suggested cards:

- Active banks
- Banks missing critical roles
- Banks with SLA violations
- High-risk banks
- Banks with no recent activity
- Banks with executive-voting delays
- Banks delayed in SWIFT or FX confirmation workflow

All cards should be clickable and open filtered entity or request views.

The focus is operational continuity and governance visibility.

---

## Primary Tabs

| Tab                 | Purpose                                                |
| ------------------- | ------------------------------------------------------ |
| All Banks           | Full institutional registry                            |
| Operational Risk    | Banks with SLA, delay, rejection, or workflow concerns |
| Missing Roles       | Banks missing required operational staffing            |
| High Activity       | Banks with large active workflow volume                |
| Inactive            | Operationally inactive or low-activity banks           |
| Government Entities | Non-bank entities registered in the platform           |

---

## Search and Filters

Recommended controls:

- Search by bank/entity name
- Search by code
- Status filter
- Risk-level filter
- SLA-risk filter
- Missing-role filter
- Active-request range
- Executive-delay filter
- SWIFT/FX-delay filter
- Last-activity range

Filter state should remain URL-shareable where practical.

---

## Institutions Oversight Table

This table should prioritize operational health and governance visibility over basic entity metadata.

Suggested columns:

| Column          | Purpose                          |
| --------------- | -------------------------------- |
| Entity          | Arabic/English name plus code    |
| Type            | Bank / Government Entity         |
| Status          | Active / Suspended / Inactive    |
| Active Requests | Current operational workload     |
| Average SLA     | Workflow performance indicator   |
| Approval Rate   | Operational quality indicator    |
| Active Users    | Staffing/activity health         |
| Missing Roles   | Critical operational gaps        |
| Last Activity   | Operational freshness            |
| Risk Flags      | Governance or workflow warnings  |
| Actions         | Oversight and governance actions |

The table should help administrators quickly identify operationally unhealthy institutions.

---

## Missing Roles Visibility

Critical staffing gaps should be surfaced prominently.

Examples:

- Missing `SWIFT_OFFICER`
- No active `BANK_REVIEWER`
- No active `DATA_ENTRY`
- No operational users available

Missing-role indicators should use strong visual emphasis because they can block workflow continuity.

---

## Risk Indicators

Institutions may display governance or operational-risk badges.

Examples:

- High rejection rate
- Repeated support returns
- Executive-voting delays
- Excessive SLA breaches
- Long-running requests
- Suspended operational users
- SWIFT upload delays
- FX confirmation delays
- High-value concentration
- Unusual inactivity

Risk indicators should remain compact but highly noticeable.

---

## SWIFT & FX Oversight

Because of the updated workflow, the bank-side post-approval responsibilities are operationally important.

The page should help identify:

- Banks delaying SWIFT upload
- Banks delaying FX request document upload
- Banks repeatedly causing post-approval delays

Suggested indicators:

- Average time from executive approval to SWIFT upload
- Average time to FX request upload
- Requests pending SWIFT beyond SLA
- Requests pending FX confirmation beyond SLA

---

## Entity Oversight Profile / Detail

Opening an entity should display an operational oversight profile rather than only an edit form.

Suggested sections:

### Overview

- Entity identity
- Status
- Users count
- Last activity
- Operational summary

### Workflow Health

- Active requests
- Average processing time
- SLA breaches
- Workflow bottlenecks
- Executive delays

### Role Coverage

- Active `DATA_ENTRY`
- Active `BANK_REVIEWER`
- Active `SWIFT_OFFICER`
- Suspended users
- MFA coverage if available

### Risk & Compliance

- Rejection spikes
- Audit alerts
- Repeated support returns
- Security-sensitive issues

### Recent Activity

- Recent workflow actions
- Recent administrative changes

### Linked Requests

- Bank-filtered requests table

The profile should support operational investigation without excessive navigation.

---

## Actions and Governance Rules

This page should avoid destructive CRUD-style behavior.

Recommended actions:

- View oversight profile
- Open requests
- Open audit view
- Export bank/entity report
- Edit entity metadata
- Suspend entity
- Reactivate entity
- Archive entity if supported

Hard delete actions should generally not exist.

Entities are historical operational records and should remain audit-traceable.

---

## Suspension Behavior

Suspension is a governance-sensitive action.

Before suspension, the UI should clearly explain operational impact.

Example warning:

- "إيقاف هذا البنك سيمنع بدء أو متابعة أي نشاط تشغيلي جديد متعلق به."

The suspension flow should require:

- Confirmation
- Reason input
- Audit logging

Where backend policy supports it, the UI should also surface:

- Active requests affected
- Pending voting sessions
- Requests waiting for SWIFT or FX completion

---

## Empty States

Empty states should remain informative and reassuring.

Examples:

- "لا توجد بنوك عالية المخاطر حالياً ✓"
- "كل البنوك تملك الأدوار التشغيلية المطلوبة حالياً"

Avoid generic empty tables where possible.

---

## UX Principles

- Treat entities as operational workflow institutions, not static records.
- Prioritize operational health and continuity visibility.
- Surface staffing gaps early.
- Make workflow bottlenecks easy to identify.
- Emphasize SWIFT and FX-confirmation delays because they are now critical post-approval stages.
- Avoid destructive CRUD behavior.
- Make risk indicators compact but immediately visible.
- Support fast drilldown into requests, audit, and workflow analysis.

---

### Document Rules (`/admin/workflow-docs`)

The Document Rules page is a workflow document governance and policy surface.

It should not behave like a simple required-documents CRUD table.

This page is sensitive because document-rule changes can affect:

- Workflow progression
- Compliance requirements
- Role handoffs
- Required uploads/downloads
- Document validation
- Active requests already in progress

The page should help CBY Admin answer:

- Which documents are required at each workflow stage?
- Which documents block workflow progression?
- Which role is responsible for uploading or generating each document?
- Which templates are active and where are they used?
- Are any document rules invalid or incomplete?
- What active requests would be affected by a rule change?

---

## Page Header

**Title:**

- "قواعد المستندات"

**Subtitle:**

- "إدارة حوكمة المستندات، القوالب، وقواعد التحقق عبر مراحل الطلب"

Primary actions:

- Add Document Rule
- Add Template
- Export Rules
- Open Rule Audit

---

## Policy Health Summary

A compact summary row appears above the policy tables.

Suggested cards:

- Active document rules
- Workflow-blocking rules
- Rules affecting FX flow
- Missing templates
- Invalid configurations
- Recently changed rules

Each card should open a filtered rule view or audit context.

---

## Primary Tabs

| Tab                     | Purpose                                                          |
| ----------------------- | ---------------------------------------------------------------- |
| Request Documents       | Documents uploaded during intake/review stages                   |
| Post-Approval Documents | SWIFT and FX request documents required after executive approval |
| Generated Documents     | System-generated documents and re-upload requirements            |
| Templates               | Downloadable templates and versions                              |
| Validation Rules        | File type, size, signature, stamp, and naming constraints        |
| Rule Audit              | Recent document-rule changes and impact history                  |

---

## Document Lifecycle Types

The UI should distinguish between different document lifecycle models.

| Type                    | Meaning                                                                                          |
| ----------------------- | ------------------------------------------------------------------------------------------------ |
| Uploaded                | User uploads a document                                                                          |
| Generated               | System generates a document                                                                      |
| Generated + Re-uploaded | System generates a document, user downloads/signs/stamps externally, then re-uploads signed copy |
| Template-based Upload   | User downloads a template, fills/signs/stamps it, then uploads the completed version             |

This distinction is important for the external FX confirmation workflow.

---

## Updated Workflow-sensitive Document Rules

The page must reflect the updated workflow:

- Executive voting opens automatically after support approval
- SWIFT is not required before executive voting
- SWIFT and FX request documents are required after executive approval
- External FX confirmation is completed after SWIFT upload / FX confirmation pending

Important rules to represent clearly:

- `SWIFT_DOCUMENT` is uploaded by `SWIFT_OFFICER` after `EXECUTIVE_APPROVED`
- `FX_CONFIRMATION_REQUEST` is uploaded by `SWIFT_OFFICER` after `EXECUTIVE_APPROVED`
- `FX_CONFIRMATION_REQUEST_TEMPLATE` is downloadable by `SWIFT_OFFICER`
- `EXTERNAL_FX_CONFIRMATION` is generated by the system and completed by `COMMITTEE_DIRECTOR` through signed/stamped re-upload

---

## Rules Table

Suggested columns:

| Column                   | Purpose                                                             |
| ------------------------ | ------------------------------------------------------------------- |
| Document / Rule Name     | Human-readable document or policy name                              |
| Lifecycle Type           | Uploaded / Generated / Template-based / Generated + Re-uploaded     |
| Required At Stage        | Workflow stage where rule applies                                   |
| Responsible Role         | Role expected to upload/download/complete                           |
| Blocks Progression       | Whether missing/invalid document prevents moving forward            |
| Template                 | Linked template if applicable                                       |
| Validation               | PDF only, max size, signature/stamp requirements, naming convention |
| Active Requests Affected | Estimated active request impact                                     |
| Status                   | Active / Draft / Disabled                                           |
| Actions                  | View, edit, duplicate, audit                                        |

The table should make blocking and stage-impact rules highly visible.

---

## Post-Approval Documents Tab

This tab focuses on documents required after executive approval.

Required focus:

- SWIFT PDF
- FX confirmation request PDF
- FX confirmation request template download

Each rule should clearly show:

- Required stage: `EXECUTIVE_APPROVED` / `WAITING_FOR_SWIFT`
- Responsible role: `SWIFT_OFFICER`
- Required file type: PDF
- Whether both documents are required before leaving SWIFT stage
- Whether the document blocks transition to `FX_CONFIRMATION_PENDING`

---

## Generated Documents Tab

This tab manages system-generated document behavior.

Important document:

- External FX Confirmation (`تأكيد مصارفة خارجية`)

Lifecycle:

- System generates PDF
- Committee Director downloads it
- Committee Director signs/stamps externally
- Committee Director uploads signed/stamped PDF
- Request moves to `COMPLETED`

Rule details should show:

- Generated at stage: `FX_CONFIRMATION_PENDING`
- Completed by: `COMMITTEE_DIRECTOR`
- Signed/stamped upload required: yes
- Blocks completion: yes
- Allowed upload type: PDF

---

## Templates Tab

Templates should be managed as versioned governance assets.

Suggested columns:

| Column          | Purpose                                |
| --------------- | -------------------------------------- |
| Template Name   | Human-readable template name           |
| Used For        | Document/rule using this template      |
| Stage           | Workflow stage where template is used  |
| Current Version | Active version                         |
| Last Updated    | Last template update                   |
| Updated By      | Admin who changed it                   |
| Active          | Active / Inactive                      |
| Download Count  | Usage signal if available              |
| Actions         | Download, replace, view history, audit |

Important template:

- `FX_CONFIRMATION_REQUEST_TEMPLATE` / "نموذج طلب تأكيد مصارفة"

Replacing templates should require a reason and audit log.

---

## Validation Rules Tab

Validation rules should be explicit and reviewable.

Examples:

- PDF only
- Maximum file size
- Required signature
- Required stamp
- Required template version
- File naming convention
- Required metadata fields

Validation rules should show whether they are hard-blocking or warning-only.

---

## Rule Detail Drawer / Page

Opening a rule should show an impact-aware detail view.

Suggested sections:

- Rule summary
- Related workflow stages
- Responsible roles
- Document lifecycle
- Template link/version
- Validation requirements
- Blocking behavior
- Affected active requests
- Recent audit history
- Before/after change history

This view should support investigation before editing.

---

## Impact Preview Before Save

Before saving changes to a document rule, the UI should show an impact preview.

Examples:

- Active requests affected
- Banks affected
- Workflow stages affected
- Whether a blocking rule is being added/removed
- Whether existing in-progress requests may become invalid

High-risk changes should require:

- Confirmation
- Mandatory reason
- Audit logging

---

## Dangerous Change Warnings

Examples of high-risk changes:

- Disabling SWIFT requirement
- Disabling FX request requirement
- Disabling signed/stamped external FX confirmation upload
- Changing responsible role for a blocking document
- Removing template requirement
- Changing allowed file type from PDF

Warnings should clearly explain workflow/compliance impact.

Example:

- "This change may allow requests to progress without required SWIFT documentation."

---

## Actions and Boundaries

Allowed actions:

- View rule
- Add/edit rule
- Duplicate rule
- Disable/enable rule
- Manage templates
- View rule audit
- Export rules

Hard delete should be avoided or strongly restricted.

Disabled rules should remain historically visible for auditability.

---

## UX Principles

- Treat document rules as workflow policy, not simple settings.
- Make stage, role, and blocking behavior visible at all times.
- Clearly separate uploaded, generated, template-based, and generated/re-uploaded documents.
- Use impact preview before saving changes.
- Require audit trail and reason for high-risk changes.
- Keep SWIFT and FX confirmation rules aligned with the updated workflow.
- Avoid customs terminology; use external FX confirmation terminology.

---

### Permissions Reference (`/admin/roles`)

The Permissions Reference page is a governance and access-intelligence surface.

It should not behave like a raw ACL or permission dump.

Its purpose is to help CBY Admin understand:

- Which role owns which workflow stage
- Which roles can perform which actions
- Which roles are visibility-only
- Which roles can upload, approve, vote, or finalize
- Which workflow constraints are critical
- Which roles are operationally blocking if unavailable
- Which document authorities belong to each role
- Which governance rules protect workflow separation and compliance

This page is intentionally read-only.

CBY Admin can inspect governance structure but should not edit runtime permissions directly from this page.

---

## Page Header

**Title:**

- "الصلاحيات والحوكمة"

**Subtitle:**

- "مرجع رقابي يوضح الأدوار، الصلاحيات، ملكية المراحل، والقيود التشغيلية"

Primary actions:

- Export Governance Reference PDF
- Open Role Audit
- Refresh

No edit-permissions action is shown.

---

## Governance Summary Row

A compact governance-health summary row appears below the header.

Suggested cards:

- Total Roles
- Bank-side Roles
- CBY-side Roles
- Approval-capable Roles
- Document-upload Roles
- Governance-critical Roles

Cards should be informational and clickable.

Examples:

- Clicking `Document-upload Roles` filters roles with upload authority.
- Clicking `Governance-critical Roles` filters roles that can block workflow continuity.

---

## Role Categories

Roles should be visually grouped by institutional ownership.

### Bank-side Roles

- `DATA_ENTRY`
- `BANK_REVIEWER`
- `SWIFT_OFFICER`
- `BANK_ADMIN`

### CBY-side Roles

- `SUPPORT_COMMITTEE`
- `EXECUTIVE_MEMBER`
- `COMMITTEE_DIRECTOR`
- `CBY_ADMIN`

The visual separation should reinforce the difference between:

- operational bank workflow roles
- CBY governance and oversight roles

---

## Role Governance Cards

Each role should appear as a governance-focused card or expandable panel.

Each card should summarize:

| Section              | Purpose                                      |
| -------------------- | -------------------------------------------- |
| Role Purpose         | Human-readable operational purpose           |
| Workflow Ownership   | Workflow stages owned by the role            |
| Can Perform          | High-value actions the role can execute      |
| Cannot Perform       | Important forbidden actions                  |
| Visibility Scope     | Own bank vs all banks                        |
| Document Authority   | Upload/download authority                    |
| Critical Constraints | Separation rules or exclusivity constraints  |
| Operational Risk     | What happens if the role becomes unavailable |

Examples:

### `SWIFT_OFFICER`

- Owns post-executive-approval SWIFT and FX-request upload steps
- Uploads:
  - SWIFT PDF
  - FX confirmation request PDF
- Cannot vote or finalize workflow
- Operational risk:
  - Requests cannot progress to FX confirmation without active SWIFT handling

### `COMMITTEE_DIRECTOR`

- Final workflow authority
- Cannot also hold `EXECUTIVE_MEMBER`
- Responsible for:
  - voting finalization
  - signed/stamped external FX confirmation completion
- Operational risk:
  - workflow completion blocked if unavailable

---

## Workflow Ownership Visualization

The page should include a visual workflow ownership map.

Example sequence:

`DATA_ENTRY`
→ `BANK_REVIEWER`
→ `SUPPORT_COMMITTEE`
→ `EXECUTIVE_MEMBER`
→ `SWIFT_OFFICER`
→ `COMMITTEE_DIRECTOR`

Each stage block should show:

- Responsible role
- Key actions
- Blocking authority
- Required documents
- Automatic transitions where applicable

Examples:

- Support approval automatically opens executive voting
- Executive approval moves request to waiting-for-SWIFT
- Signed/stamped external FX confirmation upload completes workflow

The visualization should emphasize operational handoff integrity.

---

## Document Authority Matrix

A dedicated document-authority section should exist because the updated workflow includes multiple post-approval document stages.

Suggested matrix:

| Role                 | Request Documents | SWIFT PDF | FX Request PDF | External FX Confirmation           |
| -------------------- | ----------------- | --------- | -------------- | ---------------------------------- |
| `DATA_ENTRY`         | Upload/View       | No        | No             | No                                 |
| `BANK_REVIEWER`      | View              | View      | No             | No                                 |
| `SWIFT_OFFICER`      | View              | Upload    | Upload         | No                                 |
| `EXECUTIVE_MEMBER`   | View              | View      | View           | No                                 |
| `COMMITTEE_DIRECTOR` | View              | View      | View           | Upload signed/stamped confirmation |
| `CBY_ADMIN`          | View              | View      | View           | View only                          |

The page should clearly distinguish between:

- upload authority
- download authority
- generated-document authority
- signed/stamped completion responsibility

---

## Critical Governance Rules

A dedicated governance-rules section should highlight critical operational protections.

Examples:

- `COMMITTEE_DIRECTOR` cannot also be `EXECUTIVE_MEMBER`
- Voting cannot close until all active executive members vote
- `CBY_ADMIN` has visibility but not workflow authority
- SWIFT upload occurs after executive approval
- External FX confirmation requires signed/stamped upload
- Reviewers cannot review their own requests
- Bank-side users cannot access other banks' requests
- Generated workflow documents remain audit-traceable

Rules should use strong visual emphasis because they represent governance protections, not optional UX guidance.

---

## Surface Access Matrix

A simplified surface-access matrix should appear below the governance sections.

The matrix should focus on platform surfaces rather than low-level technical permissions.

Suggested surfaces:

| Surface         | DATA_ENTRY | REVIEWER | SWIFT       | SUPPORT | EXECUTIVE | DIRECTOR         | CBY_ADMIN      |
| --------------- | ---------- | -------- | ----------- | ------- | --------- | ---------------- | -------------- |
| Dashboard       | ✓          | ✓        | ✓           | ✓       | ✓         | ✓                | ✓              |
| Requests        | ✓          | ✓        | ✓           | ✓       | ✓         | ✓                | ✓              |
| Voting          | No         | No       | No          | No      | Vote only | Full lifecycle   | Oversight only |
| FX Confirmation | No         | View     | Upload docs | No      | View      | Final completion | View only      |
| Audit           | No         | Limited  | Limited     | Limited | Limited   | Full             | Full           |
| Reports         | Limited    | Limited  | Limited     | Limited | Limited   | Full             | Full           |
| Users           | No         | No       | No          | No      | No        | No               | Full           |
| Entities        | No         | No       | No          | No      | No        | No               | Full           |
| Workflow Docs   | No         | No       | No          | No      | No        | No               | Full           |

The purpose is operational clarity, not permission-engine implementation detail.

---

## Forbidden Actions Reference

A dedicated forbidden-actions section should exist because governance boundaries are operationally important.

Examples:

### `DATA_ENTRY` cannot:

- approve requests
- reject requests
- vote
- upload SWIFT
- upload FX confirmation

### `EXECUTIVE_MEMBER` cannot:

- finalize voting
- upload SWIFT
- complete external FX confirmation

### `CBY_ADMIN` cannot:

- cast executive votes
- finalize workflow
- upload SWIFT
- upload signed FX confirmation

This section helps reduce role confusion and governance misuse.

---

## Search and Filters

Recommended controls:

- Search roles
- Search actions
- Search surfaces
- Filter approval roles
- Filter upload-capable roles
- Filter governance-critical roles
- Filter read-only roles

Examples:

- "Who can upload SWIFT?"
- "Who can finalize voting?"
- "Which roles can view audit data?"

The page should support fast governance investigation.

---

## Role Audit Integration

Each role card should support quick access to:

- Recent role changes
- Sensitive permission-related audit events
- Access-denial trends
- Governance-related incidents

This integration should remain read-only.

---

## UX Principles

- Treat the page as governance intelligence, not technical ACL management.
- Prioritize operational clarity over permission complexity.
- Emphasize workflow ownership and blocking authority.
- Make document authority highly visible.
- Surface critical governance constraints prominently.
- Keep the page fully read-only.
- Avoid exposing low-level backend permission strings as the primary UX.
- Support exportable governance-reference documentation.

### Settings (`/settings`)

The CBY Admin settings area is a governance-sensitive platform configuration surface.

It should not feel like a generic preferences page.

Changes made here may affect:

- authentication and access behavior
- notification delivery
- workflow timing rules
- SLA monitoring
- audit visibility
- platform security posture
- demo/training environments
- infrastructure integrations

All sensitive changes must be audit logged.

---

## Settings Layout

The settings area should use a structured multi-tab layout.

Recommended tabs:

| Tab                | Purpose                                            |
| ------------------ | -------------------------------------------------- |
| General            | Platform identity and operational defaults         |
| Security           | Authentication, MFA, session, and lockout policies |
| Notifications      | Email and system notification behavior             |
| Workflow & SLA     | Workflow timers and operational thresholds         |
| Integrations       | SMTP and external integrations                     |
| Audit & Compliance | Audit retention and governance settings            |

A persistent unsaved-changes bar should appear when settings are modified.

High-risk settings should require:

- confirmation modal
- mandatory reason
- audit logging

---

## General Tab

Used for low-risk operational platform configuration.

Suggested settings:

- Platform name
- Arabic/English organization labels
- Default timezone
- Default date/time format
- Maintenance banner message
- Demo/training mode visibility
- Default export branding

Demo mode must remain visually separated from production behavior.

If enabled:

- the UI should show a clear "Demo Mode" badge
- audit logs should record activation/deactivation
- workflow actions may optionally use sandbox behavior depending on backend policy

---

## Security Tab

This is one of the most sensitive configuration areas.

Suggested settings:

| Setting                   | Purpose                         |
| ------------------------- | ------------------------------- |
| MFA enforcement           | Require MFA for sensitive roles |
| Session timeout           | Auto logout threshold           |
| Password policy           | Complexity and expiration rules |
| Login rate limit          | Brute-force protection          |
| Account lockout duration  | Security lock policy            |
| Allowed IP restrictions   | Optional access restriction     |
| Device/session visibility | Session tracking behavior       |

High-risk changes should show impact warnings.

Example:

- "تقليل مدة انتهاء الجلسة قد يؤدي إلى تسجيل خروج المستخدمين النشطين."

Critical security changes may require re-authentication before save.

---

## Notifications Tab

Controls operational and governance notifications.

Suggested sections:

### Workflow Notifications

Examples:

- Request submitted
- Support claim assigned
- Voting session opened
- Voting pending reminders
- SWIFT upload pending
- FX confirmation pending
- SLA breach alerts

### Delivery Channels

- Email
- In-app notifications
- SMS if supported later

### Reminder Policies

Examples:

- Executive voting reminder interval
- SWIFT delay escalation timing
- FX confirmation delay escalation timing
- SLA warning thresholds

Notification preview/testing should exist where practical.

---

## Workflow & SLA Tab

This tab manages operational workflow timing and escalation behavior.

Suggested settings:

| Setting                            | Purpose                    |
| ---------------------------------- | -------------------------- |
| SLA thresholds by stage            | Operational timing targets |
| Executive voting escalation timing | Delayed-vote alerts        |
| SWIFT upload SLA                   | Post-approval bank timing  |
| FX confirmation SLA                | Final completion timing    |
| Support claim expiry               | Claim inactivity behavior  |
| Dashboard risk thresholds          | Oversight severity tuning  |

Important workflow notes should appear inline:

- Executive voting opens automatically after support approval
- Voting cannot close until all active executive members vote
- SWIFT upload occurs after executive approval
- FX confirmation completion requires signed/stamped upload by Committee Director

Changing SLA thresholds should preview impacted dashboards/reports where possible.

---

## Integrations Tab

Infrastructure and communication integrations.

Suggested sections:

### SMTP Configuration

- SMTP host
- Port
- Encryption
- Username
- Sender address
- Sender name
- Test email action

### Storage / Document Services

If supported later:

- object storage configuration
- backup integration
- document scanning services

### External Services

Reserved for future integrations.

Examples:

- national registries
- compliance systems
- identity verification

Secrets and credentials must never be fully exposed after save.

---

## Audit & Compliance Tab

Controls governance and audit-related behavior.

Suggested settings:

| Setting                       | Purpose                 |
| ----------------------------- | ----------------------- |
| Audit retention duration      | Evidence retention      |
| Sensitive-event thresholds    | Risk visibility tuning  |
| Export watermarking           | Compliance traceability |
| Access-denial alerting        | Governance monitoring   |
| Investigation export defaults | Evidence generation     |

This tab should clearly explain that audit data is immutable historical evidence.

---

## Settings UX Principles

- Separate low-risk preferences from governance-sensitive settings.
- Make dangerous changes visually distinct.
- Explain operational impact before save.
- Always audit log sensitive configuration changes.
- Use inline guidance instead of technical jargon.
- Prevent accidental production-impacting actions.
- Keep security settings more prominent than cosmetic settings.

---

### Profile (`/profile`)

The CBY Admin profile page is an identity, access, and security-awareness surface.

It should feel more security-focused than a normal personal-profile page.

The layout should use a structured 3-column arrangement.

---

## Left Column — Identity Card

Primary identity summary.

Suggested content:

- Profile photo/avatar
- Full name
- Email
- CBY role badge
- Account status
- MFA status badge
- Last login timestamp
- Last active session/device

Quick actions:

- Change password
- Manage MFA
- View active sessions

The page should visually reinforce that this account has governance-level authority.

---

## Center Column — Account & Security

Focused on personal security and access visibility.

### Personal Information

- Full name
- Email
- Preferred language
- Timezone

### Security Section

Suggested items:

- MFA enabled/disabled
- Password last changed
- Active sessions count
- Trusted devices if supported
- Recent security events

### Active Sessions

Display:

- Device/browser
- IP address
- Last activity
- Session location if available

Allow:

- Revoke session
- Revoke all other sessions

Sensitive actions should require confirmation.

---

## Right Column — Governance Activity

This column provides lightweight governance-awareness context.

Suggested cards:

- Recent administrative actions
- Recent audit-sensitive events
- Recent exports performed
- Role changes involving this user
- Recent permission denials
- Security alerts related to this account

This section is informational and read-only.

---

## Profile UX Principles

- Emphasize security awareness over personalization.
- Make MFA and session visibility highly prominent.
- Keep sensitive actions visually separated.
- Surface recent governance-sensitive activity clearly.
- Reinforce audit transparency for privileged accounts.
- Avoid social-style profile presentation.
