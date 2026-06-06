---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
lastStep: 8
status: 'complete'
completedAt: '2026-06-06'
inputDocuments:
  - docs/00-project-brief.md
  - docs/01-workflow-and-business-rules.md
  - docs/02-system-architecture.md
  - docs/03-database-and-models.md
  - docs/06-api-reference.md
  - docs/07-account-recovery-and-mail.md
  - _bmad-output/planning-artifacts/project-context.md
  - _bmad-output/planning-artifacts/sprint-change-proposal-2026-06-05-account-recovery-reconciliation.md
workflowType: 'architecture'
project_name: 'Yemen Flow Hub'
user_name: 'MAJED'
date: '2026-06-06'
scope: 'Email / Notification Subsystem'
---

# Architecture Decision Document — Email / Notification Subsystem

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Scope

Architecture for the Yemen Flow Hub email + notification subsystem: notification-type registry, dual-source template resolver, email design system, queue-based delivery, delivery log / outbox, and security/compliance posture. Locked decisions supplied by MAJED on activation are treated as approved constraints.

## Project Context Analysis

### Requirements Overview

**Brownfield note:** This subsystem formalizes existing ad-hoc mail scaffolding (8 `Notification` classes, 6 `Mail` classes, 7 email Blade views, `SendWorkflowNotifications` listener, standard Laravel `notifications` table) into a governed subsystem. Existing classes are migrated into the registry — not greenfielded.

**Existing infra baseline (verified in code):**
- `config/queue.php`: `default = redis`, single `default` queue → dedicated email queue + isolation is NEW work.
- `QUEUE_FAILED_DRIVER = database-uuids` → dead-letter substrate exists; email-specific retry/backoff policy is NEW.
- `config/mail.php`: vanilla smtp/array/log mailers, no markdown theme override, `from` fallback present → theme override + `config/email-theme.php` is NEW.
- `MfaService` already isolates OTP in its own cache namespace (recovery doctrine mandates password-reset OTP stay separate from login MFA OTP).
- Production fail-fast already rejects non-SMTP / Mailpit-host in `production` (preserve).

**Functional Requirements (derived):**

| # | FR | Architectural implication |
|---|---|---|
| FR1 | Notification-type registry (enum-backed, central) | New enum + registry config; per-type flags drive channels / persist_body / admin_editable / allowed_variables |
| FR2 | Dual delivery channel: database always + mail conditional | `via()` resolves from registry per type and per-recipient preference |
| FR3 | Dual-source template resolver (DB admin-editable vs Blade system-managed) | Resolver service; admin owns Markdown prose only, system owns layout |
| FR4 | Template versioning, reproducible active-version-at-send | `notification_templates` + `notification_template_versions` tables |
| FR5 | Save/render validation (allowed-var whitelist, raw-HTML strip, MD sanitize, undefined-var guard, safe fallback) | Validation pipeline at save AND render |
| FR6 | Template + sample-data rendered preview | Preview endpoint with fixed sample data (YFH-2026-000123 / Yemen International Bank / 1,000,000 USD / Approved) |
| FR7 | Delivery log / outbox (full audit field set) | `email_deliveries` table; persist_body full vs redacted per type |
| FR8 | Idempotency (event_id, user_id, channel) — no double-send on retry | Unique idempotency key + pre-send guard |
| FR9 | Email design system: theme override + `<x-email.*>` components | Blade components: status-badge, data-row, info-box (variant prop), action-card, otp-code, confidentiality-notice |
| FR10 | RTL + Arabic default, system Arabic font fallback, plaintext multipart always | Inline-style tokens from `config/email-theme.php`; no webfont; no Tailwind in email |
| FR11 | Phase-1 types: REQUEST_APPROVED, REQUEST_REJECTED, REQUEST_RETURNED, VOTING_OPENED, MFA_OTP, PASSWORD_RESET | Registry seeded; existing Mail/Notification classes mapped in |
| FR12 | Per-recipient locale resolved at render time | Locale resolution inside render pipeline |

**Non-Functional Requirements:**

| NFR | Driver |
|---|---|
| Audit reproducibility | Banking compliance — read log, reproduce exact historical email, NO re-render |
| Security: never store OTP/reset tokens/secrets; redact MFA_OTP / PASSWORD_RESET body (mask `••••••`) | Compliance non-negotiable |
| SPF + DKIM + DMARC on CBY sending domain; no tracking pixels; no sensitive financials in body (deep-link to app) | Infra + data-protection mandate |
| Org-scoped sends — never email a user about an out-of-scope request | Reuses workflow `scopeForUser()` invariant |
| OTP / reset: short stated TTL, single-use, rate-limited send | Recovery doctrine (`docs/07-account-recovery-and-mail.md`) |
| Queue isolation + retry/backoff/dead-letter | Delivery reliability; workflow events must not block on SMTP |
| Provider portability (Laravel mail abstraction) | Scale readiness — swap CBY SMTP without code change |
| Production fail-fast: reject non-SMTP / Mailpit host in `production` env | Preserve existing doctrine |
| Workflow-comm sends tied to existing `audit_logs` | Single audit spine, no parallel audit |

**Scale & Complexity:**

- Primary domain: backend (Laravel 11) — service + queue + persistence; admin-facing template-management UI (Nuxt) is secondary.
- Complexity level: **HIGH (enterprise / compliance-driven)** — governance + audit + reproducibility + security + RTL/i18n + dual-template ownership, not request volume.
- Estimated new architectural components: **~9** — NotificationType enum/registry, TemplateResolver, validation pipeline, `config/email-theme.php`, `<x-email.*>` component set, EmailDelivery outbox (model + service), idempotency guard, dedicated email queue config, preview/render endpoint.

### Technical Constraints & Dependencies

- **MUST reuse:** `audit_logs` spine + `AuditService`; `notifications` table (database channel); `MfaService` cache-namespace separation; `WorkflowService` event hook (`RequestTransitioned` → `SendWorkflowNotifications` listener).
- **MUST NOT:** store secrets/OTPs; use Tailwind or webfonts in email; configure Mailpit as a production provider; add a parallel audit log.
- DESIGN.md is the single source for email tokens, mirrored to `config/email-theme.php` and applied as inline styles only.
- SMTP transport via `infra/.env` only — no DB/UI SMTP config. (Contrast: template bodies ARE DB-managed; transport is NOT.)
- Laravel Markdown Mail (NO MJML — Phase 2 re-eval only).
- Laravel Notifications `via()` = database always + mail conditional.

### Cross-Cutting Concerns Identified

1. **Audit / reproducibility** — spans outbox + template versioning + render pipeline.
2. **Security / redaction** — spans registry flags + outbox persist_body + logging guards.
3. **i18n / RTL** — spans theme + components + locale-at-render.
4. **Idempotency / reliability** — spans queue + outbox + retry.
5. **Registry as extension point** — adding a type = data + template, never refactor; governs the shape of every other component.
6. **Dual ownership boundary** — admin owns prose; system owns layout / badge / data-rows / buttons / footer; governs resolver + validation + preview.

## Starter Template Evaluation

### Verdict: NOT APPLICABLE — brownfield subsystem

No starter template applies. The email subsystem is built inside the existing Laravel 11 backend and existing Nuxt 4 frontend. The tech stack is already locked by the project (`project-context.md` §2); there is no greenfield scaffold to initialize. This step is reframed as: foundation libraries + tooling already present vs. NEW for this subsystem.

### Technology Foundation (inherited, not chosen)

| Layer | Tech | Status |
|---|---|---|
| Runtime | PHP 8.2+, Laravel 11 | Inherited |
| Mail engine | Laravel Mail + Notifications (markdown mailables) | Inherited, formalize |
| Queue | Redis (`queue.default = redis`) | Inherited |
| Failed / dead-letter | `database-uuids` failed driver | Inherited |
| DB | MySQL | Inherited |
| Cache / TTL | Redis (OTP namespace via `MfaService`) | Inherited |
| PDF (not email) | barryvdh/laravel-dompdf | Inherited, unrelated |
| Admin UI | Nuxt 4 / Vue 4 / shadcn-vue | Inherited |

### NEW Components This Subsystem Introduces (no new framework)

| Component | Built on |
|---|---|
| `NotificationType` enum + registry | native PHP enum + config array |
| `config/email-theme.php` | native config, mirrors DESIGN.md |
| `<x-email.*>` Blade components | native Blade components |
| Mail theme override (message / button / panel) | Laravel markdown-mail theme publish |
| `email_deliveries` outbox + `EmailDeliveryService` | Eloquent + migration |
| `notification_templates` + `notification_template_versions` | Eloquent + migration |
| `TemplateResolver` + validation pipeline | native services |
| Dedicated `emails` queue | `config/queue.php` connection/queue entry |

### Library / Dependency Decision

Only template-body sanitization (FR5) is a candidate for a new dependency. Decision: **zero new dependency**.

- **Markdown parser:** reuse `league/commonmark` (already a Laravel dependency). No new dep.
- **HTML handling:** locked decision is "Markdown PROSE ONLY + raw-HTML strip." Use CommonMark with `html_input => 'strip'` and `allow_unsafe_links => false` so no author-supplied HTML survives rendering. This is the strongest security posture, matches the prose-only mandate, and adds no supply-chain surface — preferred for a government compliance context over `mews/purifier` / HTMLPurifier.

**Rationale:** Adding a sanitizer dependency would be defense for HTML that is never allowed in the first place. Stripping at parse time removes the threat class entirely (YAGNI + reduced audit surface).

**Note:** No project-initialization story is needed (brownfield). The first implementation story is the registry + enum + migrations foundation, not a scaffold command.

## Core Architectural Decisions

### Decision Priority Analysis

**Critical (block implementation):**
- D1 — Registry-driven recipient resolution via org-scoped query
- D2 — Outbox dedup via unique DB index, insert-first
- D3 — Dispatch on `after_commit` (email queue)
- D4 — Template stored as raw Markdown, rendered at send
- D5 — OTP outbox = redacted re-render (code never in DB)

**Important (shape architecture):**
- D6 — Outbox `rendered_body` inline TEXT, no Phase-1 pruning
- D7 — `users.locale` column, default `ar`
- D8 — Dedicated `emails` queue + dedicated worker

**Deferred (Phase 2):**
- Outbox archival / pruning (revisit at proven volume)
- MJML re-evaluation
- Localization-as-variant beyond `ar` / `en`
- Digest notification types (taxonomy reserved now, build later)

### Data Architecture

- **New tables:** `notification_templates`, `notification_template_versions`, `email_deliveries`. New column: `users.locale` (nullable, default `ar`).
- **D4 — Template body storage:** each `notification_template_versions` row stores **raw Markdown source** + metadata (`changed_by`, `changed_at`, active flag). HTML is never stored in the version. At send, render Markdown → HTML, then **snapshot the rendered output into `email_deliveries`**. Source stays editable/diffable; the outbox row is the immutable historical artifact.
- **D6 — Outbox body:** `rendered_subject` + `rendered_body` stored as MySQL `TEXT` / `LONGTEXT` inline on `email_deliveries`. No pruning in Phase 1 — banking audit favors permanent retention.
- **D2 — Idempotency:** unique index on `(event_id, user_id, channel)`. **Insert the `email_deliveries` row (status = `queued`) BEFORE dispatch.** A duplicate insert fails the unique constraint → the send is skipped. DB is the single source of truth; safe across worker restarts and races.
- **Reproducibility:** reading one `email_deliveries` row reproduces the exact historical email with no re-render.

### Authentication & Security

- **D5 — OTP redaction:** the live code appears in the sent email only. The outbox stores a **redacted re-render** of the same template with the code variable masked (`••••••`). The code/token is never written to the DB or to logs. Applies to `MFA_OTP` and `PASSWORD_RESET` (`persist_body = redacted`).
- OTP / reset stay in a separate cache namespace (recovery doctrine): short TTL, single-use, rate-limited send.
- No tracking pixels. No sensitive financials in the body → deep-link to the app.
- SPF + DKIM + DMARC infra mandate on the CBY sending domain.
- Production fail-fast (non-SMTP / Mailpit host in `production`) preserved.

### API & Communication Patterns

- **D1 — Recipient resolution:** each `NotificationType` declares its target role(s) / relation in the registry. A resolver runs an **org-scoped query** (reusing the `scopeForUser()` invariant) to produce concrete recipients. Org-scope is enforced at the query level — the design structurally cannot email an out-of-scope user.
- **D3 — Dispatch timing:** the email queue connection opts into `after_commit = true`. Jobs dispatch only after the `WorkflowService::transition()` DB transaction commits, so a rolled-back transition never produces an email.
- Workflow-comm sends are tied to the existing `audit_logs` spine (no parallel audit).
- Admin template management is an authenticated `CBY_ADMIN` API exposing template preview + sample-data rendered preview; validation runs at save AND at render.

### Frontend Architecture

- Admin template-management UI (Nuxt 4 / shadcn-vue): list types, edit Subject + Markdown body, view version history, and a **dual preview** — template preview + sample-data rendered preview using fixed sample data (YFH-2026-000123 / Yemen International Bank / 1,000,000 USD / Approved).
- No new frontend framework; reuse existing settings/admin surface patterns.

### Infrastructure & Deployment

- **D8 — Queue topology:** `config/queue.php` gains an `emails` queue; a dedicated `queue:work --queue=emails` worker runs it. SMTP latency or failure never blocks workflow/default jobs. Retry/backoff plus the existing `database-uuids` dead-letter driver.
- **D7 — Locale:** the render pipeline reads `recipient.locale` (default `ar`).
- SMTP via `infra/.env`: CBY government SMTP in prod, Mailpit in dev. Provider-portable through the Laravel mail abstraction.

### Decision Impact Analysis

**Implementation sequence:**
1. `NotificationType` enum + registry config (per-type flags) + `users.locale` migration.
2. `email_deliveries` migration (unique idempotency index) + `EmailDeliveryService` (insert-first).
3. `config/email-theme.php` + mail theme override + `<x-email.*>` components.
4. `notification_templates` + `notification_template_versions` migrations + `TemplateResolver` + validation pipeline.
5. Wire `SendWorkflowNotifications` → registry resolver + `after_commit` dispatch + outbox record.
6. Migrate the existing 6 `Mail` / 8 `Notification` classes into the registry.
7. Admin template API + preview endpoints + Nuxt management UI.

**Cross-component dependencies:**
- The registry (1) governs the shape of the resolver (5), outbox `persist_body` (2), template editability (4), and validation allowed-vars (4).
- Outbox insert-first (2) gates dispatch (5) — idempotency is enforced before send.
- Theme + components (3) are consumed by both Blade system templates and rendered admin Markdown (4).

## Implementation Patterns & Consistency Rules

### Critical Conflict Points Identified

9 areas where AI agents could diverge on this subsystem. Most naming is inherited from the existing codebase and is restated here to bind agents. Verified against current code: `AuditAction` already declares `EMAIL_TEST_SENT`, `EMAIL_TEMPLATE_UPDATED`, `EMAIL_DELIVERY_FAILED` (audit hooks pre-staged); existing notifications use `SCREAMING_SNAKE` enum cases and `via()` returning `['database']`; migrations use `YYYY_MM_DD_NNNNNN_verb_noun`.

### Naming Patterns

**Notification types (enum cases):** `SCREAMING_SNAKE`, matching existing `AuditAction` — e.g. `REQUEST_APPROVED`, `MFA_OTP`. Not `RequestApproved` / `requestApproved`.

**Audit actions — REUSE existing, do NOT invent:** `EMAIL_TEMPLATE_UPDATED` (template save), `EMAIL_DELIVERY_FAILED` (failed send), `EMAIL_TEST_SENT` (test email). Already in `app/Enums/AuditAction.php`. Agents must NOT add `EMAIL_SENT`-style duplicates.

**DB tables:** snake_case plural — `notification_templates`, `notification_template_versions`, `email_deliveries`. Columns snake_case: `recipient_user_id`, `rendered_body`, `template_version_id`, `provider_message_id`. FK = `{singular}_id`.

**Migrations:** `YYYY_MM_DD_NNNNNN_verb_noun.php` (e.g. `..._create_email_deliveries_table.php`, `..._add_locale_to_users_table.php`).

**Blade email components:** `<x-email.{kebab}>` — `status-badge`, `data-row`, `info-box`, `action-card`, `otp-code`, `confidentiality-notice`. Files at `resources/views/components/email/{kebab}.blade.php`.

**Theme tokens (`config/email-theme.php`):** snake_case keys mirroring DESIGN.md names — `primary_blue`, `severity_red`, `voting_indigo`. No raw hex scattered in components.

**Services:** `{Domain}Service` / resolver — `EmailDeliveryService`, `TemplateResolver`, `TemplateValidator`, `NotificationRegistry`. Namespace `App\Services\Notifications\`.

### Structure Patterns

```
backend/app/
  Enums/NotificationType.php
  Services/Notifications/
    NotificationRegistry.php        (per-type config accessor)
    TemplateResolver.php            (DB vs Blade source)
    TemplateValidator.php           (allowed-var, strip, sanitize)
    EmailDeliveryService.php        (insert-first, outbox, redaction)
  Models/{NotificationTemplate,NotificationTemplateVersion,EmailDelivery}.php
config/email-theme.php
resources/views/
  components/email/*.blade.php       (<x-email.*>)
  vendor/mail/html/{message,button,panel}.blade.php   (theme override)
  emails/system/*.blade.php          (system-managed: otp, reset, voting)
```

Tests: `backend/tests/Feature/Notifications/` + `backend/tests/Unit/Notifications/` (matches existing Feature/Unit split).

### Format Patterns

**Registry per-type shape (frozen key set):**

```php
NotificationType::REQUEST_APPROVED => [
  'channels' => ['database', 'mail'],
  'admin_editable' => true,
  'persist_body' => 'full',          // full | redacted
  'source' => 'db',                  // db | blade
  'recipient_roles' => ['DATA_ENTRY', 'BANK_REVIEWER'],
  'allowed_variables' => ['reference_number', 'bank_name', 'amount', 'currency', 'status'],
],
```

Every type declares ALL keys — no partial maps, no ad-hoc keys.

**Outbox status enum:** `queued | sent | failed | bounced` (lowercase, locked).

**API response:** existing wrapper `{ success, message, data }` and error `{ success: false, message, error_code }`. Reuse; no new envelope.

**Allowed-variable refs in Markdown:** `{{ variable_name }}` (snake_case, whitespace-tolerant). Undefined var → safe fallback; never leak raw `{{ }}` to a recipient.

### Communication Patterns

**Dispatch:** workflow emails ONLY via the `SendWorkflowNotifications` listener → registry resolver. Agents must NOT call `Mail::send()` / `->notify()` directly from controllers/services for registered types.

**Idempotency `event_id`:** stable, derived from the domain event — `{request_id}:{to_status}` for workflow, `{user_id}:{otp_purpose}:{issued_at}` for OTP. Same event retried = same key = no double-send.

**after_commit:** all email dispatch is post-commit; no emailing inside an open transaction.

### Process Patterns

**Redaction:** `persist_body = redacted` types store a masked re-render in the outbox. Code/token NEVER reaches the DB or `Log::`. Single chokepoint: `EmailDeliveryService::record()` applies redaction by type — agents route through it, never write `email_deliveries` rows directly.

**Failure:** SMTP failure → job retry/backoff → on exhaustion set `email_deliveries.status = failed` + `error`, write audit `EMAIL_DELIVERY_FAILED`, dead-letter via `failed_jobs`. Never silently swallow.

**Validation timing:** at template SAVE (reject bad input) AND at RENDER (guard + safe fallback) — both, not either.

### Enforcement Guidelines

**All AI agents MUST:**
- Use the `NotificationType` enum + registry — never hardcode type strings or channel lists.
- Route all sends through listener → resolver → `EmailDeliveryService`.
- Reuse the existing `AuditAction` email cases.
- Org-scope recipient queries via `scopeForUser()`.
- Use inline styles from `config/email-theme.php` only — no Tailwind, no webfont, no raw hex.
- Insert the outbox row before dispatch (idempotency).

**Anti-patterns (forbidden):**
- `Mail::to($x)->send()` directly for a registered type.
- Storing an OTP / token / raw HTML in any column.
- Adding a new audit enum case that duplicates an existing email one.
- `text-red-600` or hex literals inside `<x-email.*>` components.
- Partial registry entries.

## Project Structure & Boundaries

### Complete Subsystem Tree (✚ new, ✎ modify, · exists)

```
backend/
├── app/
│   ├── Enums/
│   │   ├── AuditAction.php                          ·  (email cases already present)
│   │   └── NotificationType.php                     ✚  enum-backed registry keys
│   ├── Services/
│   │   ├── Notifications/
│   │   │   ├── ClaimReleaseNotifier.php             ·
│   │   │   ├── NotificationRegistry.php             ✚  per-type config accessor (pure config, no I/O)
│   │   │   ├── SendEmailNotification.php            ✚  ORCHESTRATOR: registry→resolver→render→record→dispatch
│   │   │   ├── TemplateResolver.php                 ✚  db vs blade source selection only
│   │   │   ├── TemplateValidator.php                ✚  allowed-var / strip / sanitize (stateless)
│   │   │   ├── TemplateRenderer.php                 ✚  markdown→html + locale + theme (no persistence)
│   │   │   └── EmailDeliveryService.php             ✚  PERSISTENCE ONLY: reserve / finalize / markSent / markFailed
│   │   └── Audit/AuditService.php                   ·  (reuse spine)
│   ├── Models/
│   │   ├── NotificationTemplate.php                 ✚
│   │   ├── NotificationTemplateVersion.php          ✚
│   │   ├── EmailDelivery.php                        ✚
│   │   └── User.php                                 ✎  + locale cast
│   ├── Notifications/                               ✎  8 existing → registry-driven via()
│   │   ├── RequestApprovedNotification.php          ✎  via() = database + mail (conditional)
│   │   ├── RequestRejectedNotification.php          ✎
│   │   ├── RequestReturnedNotification.php          ✎
│   │   ├── VotingOpenedNotification.php             ✎
│   │   └── … (Submitted, SwiftUpload, ClaimReleased, CustomsIssued)  ·
│   ├── Mail/                                        ✎  6 existing → theme + components
│   │   ├── MfaOtpMail.php                           ✎  source=blade, persist=redacted
│   │   ├── PasswordRecoveryOtpMail.php              ✎  source=blade, persist=redacted
│   │   └── … (RequestApproved/Rejected/Returned, VotingOpened)  ✎
│   ├── Listeners/
│   │   └── SendWorkflowNotifications.php            ✎  → SendEmailNotification orchestrator (after_commit)
│   └── Http/
│       ├── Controllers/Api/Admin/
│       │   └── NotificationTemplateController.php   ✚  index/show/update/preview/render-preview
│       └── Resources/
│           ├── NotificationTemplateResource.php     ✚
│           └── EmailDeliveryResource.php            ✚
├── config/
│   ├── mail.php                                     ✎  theme override pointer
│   ├── queue.php                                    ✎  emails connection/queue + after_commit
│   └── email-theme.php                              ✚  DESIGN.md tokens → inline
├── database/migrations/
│   ├── ..._create_notification_templates_table.php          ✚
│   ├── ..._create_notification_template_versions_table.php  ✚
│   ├── ..._create_email_deliveries_table.php                ✚  unique(event_id,user_id,channel)
│   └── ..._add_locale_to_users_table.php                    ✚  default 'ar'
├── database/seeders/
│   └── NotificationTemplateSeeder.php              ✚  seed 3 admin-editable DB templates
├── resources/views/
│   ├── vendor/mail/html/
│   │   ├── message.blade.php                        ✚  theme override
│   │   ├── button.blade.php                         ✚
│   │   └── panel.blade.php                          ✚
│   ├── components/email/
│   │   ├── status-badge.blade.php                   ✚
│   │   ├── data-row.blade.php                       ✚
│   │   ├── info-box.blade.php                       ✚  variant prop
│   │   ├── action-card.blade.php                    ✚
│   │   ├── otp-code.blade.php                       ✚
│   │   └── confidentiality-notice.blade.php         ✚
│   └── emails/                                      ✎
│       ├── system/                                  ✚  system-managed (blade source)
│       │   ├── mfa-otp.blade.php                    ✎ (move)
│       │   ├── password-recovery-otp.blade.php      ✎ (move)
│       │   └── voting-opened.blade.php              ✎ (move)
│       └── … (request-approved/rejected/returned now DB-sourced; blade = fallback)
└── tests/
    ├── Feature/Notifications/
    │   ├── EmailDeliveryOutboxTest.php              ✚  reserve/finalize, idempotency, redaction
    │   ├── TemplateResolverTest.php                 ✚  db vs blade
    │   ├── TemplateValidationTest.php               ✚  allowed-var, strip, undefined-var
    │   ├── NotificationTemplateApiTest.php          ✚  CBY_ADMIN only, preview
    │   └── WorkflowEmailDispatchTest.php            ✚  after_commit, org-scope, no double-send
    └── Unit/Notifications/
        ├── NotificationRegistryTest.php             ✚  frozen key set per type
        └── RedactionTest.php                        ✚  OTP never persisted

frontend/                                            (admin UI — secondary)
├── app/pages/admin/
│   └── email-templates/
│       ├── index.vue                                ✚  type list
│       └── [type].vue                               ✚  edit subject+markdown, version history, dual preview
├── app/composables/
│   └── useEmailTemplates.ts                         ✚
└── app/types/
    └── notifications.ts                             ✚  NotificationType, template, version, delivery

infra/
├── docker-compose.yml                               ✎  mailpit service (dev)
└── .env.example                                     ✎  MAIL_* + EMAIL_QUEUE entries
```

### Architectural Boundaries

**API boundaries:**
- Public: none new. OTP/reset endpoints live in the auth-recovery story; email is a downstream side-effect, not a new endpoint.
- Admin (auth:sanctum + `CBY_ADMIN`): `GET/PUT /api/admin/notification-templates`, `POST /api/admin/notification-templates/{type}/preview`. Authority is backend policy, never frontend.
- No SMTP-config endpoint — transport is env-only. (Boundary: template bodies are DB-managed; transport is NOT.)

**Send-path sequence (one direction, idempotency-first, render-before-persist):**

```
Domain event
 → SendWorkflowNotifications listener        (entry point for agents)
 → SendEmailNotification orchestrator
     → NotificationRegistry                   (who / how — channels, source, persist_body, roles, allowed_vars)
     → recipient resolver (scopeForUser)      (org-scoped concrete users; row-per-recipient)
     → EmailDeliveryService::reserve()        (INSERT status=queued; unique(event_id,user_id,channel)
                                               claims idempotency BEFORE render; duplicate → null → skip)
     → TemplateResolver                       (db vs blade source)
     → TemplateRenderer                       (markdown→html, locale, theme → rendered_subject + rendered_body)
     → EmailDeliveryService::finalize()       (write rendered snapshot, redacted per type, still queued)
     → dispatch email job on `emails` queue   (after_commit)
 → SMTP
 → EmailDeliveryService::markSent()/markFailed()   (outbox status update + provider_message_id / error)
```

**Responsibility split — explicit (resolves the insert-first vs render-before-persist tension):**
- `SendEmailNotification` is the **orchestrator** that owns the sequence. Agents enter only via the listener.
- `EmailDeliveryService` is **persistence only** — it does NOT orchestrate resolve/render. It exposes a two-phase write:
  - `reserve(event_id, user_id, channel, type)` → inserts the `queued` row; the unique index claims idempotency **before** any rendering work. Duplicate returns null → orchestrator skips.
  - `finalize(delivery, rendered_subject, rendered_body)` → fills the rendered snapshot (redaction applied here by type), still `queued`, pre-dispatch.
  - `markSent(delivery, provider_message_id)` / `markFailed(delivery, error)` → post-SMTP status.
- This keeps idempotency claimed first (cheap insert) AND guarantees the outbox stores the rendered body, with no service taking on a god-orchestrator role.

**Service boundaries:**
- `NotificationRegistry` — pure config, no I/O.
- `TemplateResolver` — source selection only (db vs blade), no rendering.
- `TemplateRenderer` — markdown→html + theme + locale; no persistence.
- `EmailDeliveryService` — persistence + redaction + idempotency; no rendering, no recipient logic.
- `TemplateValidator` — save + render guard; stateless.

**Data boundaries:**
- `notification_template_versions` — mutable source of truth for editable bodies (raw Markdown).
- `email_deliveries` — immutable historical artifact (rendered snapshot). Neither reads the other at send time.
- `users.locale` — read-only at render.
- `notifications` table reused unchanged (database channel).

### Requirements → Structure Mapping

| FR | Lives in |
|---|---|
| FR1 registry | `Enums/NotificationType.php` + `NotificationRegistry.php` |
| FR2 dual channel | `Notifications/*::via()` (registry-driven) |
| FR3 resolver | `TemplateResolver.php` |
| FR4 versioning | `NotificationTemplate(Version).php` + migrations |
| FR5 validation | `TemplateValidator.php` |
| FR6 preview | `NotificationTemplateController@preview` + `[type].vue` |
| FR7 outbox | `EmailDelivery.php` + `EmailDeliveryService.php` + migration |
| FR8 idempotency | unique-index migration + `EmailDeliveryService::reserve()` |
| FR9 design system | `components/email/*` + `vendor/mail/html/*` |
| FR10 RTL/theme | `config/email-theme.php` + `TemplateRenderer` |
| FR11 phase-1 types | `NotificationTemplateSeeder` + mapped Mail/Notification classes |
| FR12 locale | `users.locale` migration + `TemplateRenderer` |

### Integration Points

- **Internal:** workflow `RequestTransitioned` event (exists) → listener. Auth-recovery OTP/reset (exists) → `MfaOtpMail` / `PasswordRecoveryOtpMail` via registry.
- **External:** SMTP (CBY gov prod / Mailpit dev) via the Laravel mail abstraction (provider-portable). Mailpit SMTP `1025` / web `8025`, dev only.
- **Data flow:** every send produces exactly one `email_deliveries` row per recipient per channel, reserved before transport and finalized with the rendered body before dispatch.

## Architecture Validation Results

### Coherence Validation ✅

**Decision compatibility:** Decisions D1–D8 are consistent with no contradictions. The one real tension — insert-first (D2) vs render-before-persist (FR7) — is resolved via the two-phase `reserve` / `finalize` split. `after_commit` (D3) is compatible with the redis email queue, provided the new `emails` connection sets `after_commit = true` (captured as Important gap #1). Raw-Markdown storage (D4) + render-at-send aligns with the outbox snapshot (D6) and redaction (D5).

**Pattern consistency:** Naming is inherited from verified codebase conventions (`SCREAMING_SNAKE` enums, snake_case tables/migrations, `<x-email.kebab>`). Audit reuse is confirmed against real `AuditAction` cases. The registry's frozen key set enforces D1/D2/D5 uniformly.

**Structure alignment:** The send-path boundary (listener → orchestrator → services → queue) supports D1/D3. The `EmailDeliveryService` sole-writer boundary enforces D2/D5. The service split is single-responsibility with no god-orchestrator.

### Requirements Coverage Validation ✅

**FR coverage:** FR1–FR12 each map to a concrete file (step 6 mapping table). No orphan FR.

**NFR coverage:**

| NFR | Covered by |
|---|---|
| Audit reproducibility | D6 inline snapshot + outbox immutability boundary |
| No secret storage / redaction | D5 + `EmailDeliveryService::finalize()` chokepoint + RedactionTest |
| SPF / DKIM / DMARC | Infra mandate (env / DNS — out of code scope, noted) |
| Org-scoped sends | D1 `scopeForUser()` resolver |
| OTP TTL / single-use / rate-limit | Existing recovery doctrine (auth story); email is a side-effect only |
| Queue isolation + retry / dead-letter | D8 + existing `failed_jobs` |
| Provider portability | Laravel mail abstraction |
| Production fail-fast | Preserved existing guard |
| Single audit spine | Reuse `audit_logs` + existing email `AuditAction` cases |

### Implementation Readiness Validation ✅

**Decision completeness:** All critical decisions documented; no version ambiguity (zero new dependencies). Patterns are enforceable via explicit MUST / forbidden lists.

**Structure completeness:** Full tree with ✚/✎/· markers; every file located; boundaries + responsibility split explicit.

**Pattern completeness:** 9 conflict points addressed; error / redaction / validation-timing process patterns specified.

### Gap Analysis Results

**Critical gaps:** NONE.

**Important gaps (capture in the relevant stories; not blocking):**
1. The new `emails` queue connection MUST set `after_commit = true` (current redis connection is `false`). Decided; must land in `config/queue.php` in the foundation story.
2. Multi-recipient outbox = one row per recipient per channel. The idempotency key includes `user_id`, so fan-out rows are naturally distinct. Confirmed, no change.
3. DB-template missing fallback: a `source = db` type with no active DB version falls back to its Blade template. The first template story should assert this.

**Nice-to-have (Phase 2):** bounce-webhook ingestion (`status = bounced` is currently set only on synchronous provider report), digest types, archival job.

### Validation Issues Addressed

- Insert-first vs render-before-persist tension → resolved via two-phase `reserve` / `finalize` (step 6).
- `after_commit` on the new queue connection → captured as Important gap #1; decided and folded into the foundation story.

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed
- [x] Technical constraints identified
- [x] Cross-cutting concerns mapped

**Architectural Decisions**
- [x] Critical decisions documented with versions (zero new deps; inherited stack pinned by project)
- [x] Technology stack fully specified
- [x] Integration patterns defined
- [x] Performance considerations addressed (queue isolation, inline outbox, indexed idempotency)

**Implementation Patterns**
- [x] Naming conventions established
- [x] Structure patterns defined
- [x] Communication patterns specified
- [x] Process patterns documented

**Project Structure**
- [x] Complete directory structure defined
- [x] Component boundaries established
- [x] Integration points mapped
- [x] Requirements to structure mapping complete

### Architecture Readiness Assessment

**Overall Status:** READY FOR IMPLEMENTATION (16/16 checklist items confirmed, no critical gaps).

**Confidence Level:** HIGH — brownfield subsystem, decisions grounded in verified existing code, all FR/NFR mapped to concrete components.

**Key Strengths:**
- Reuses the existing audit / notification spine instead of building a parallel one.
- Idempotency + redaction structurally enforced through a single-writer boundary.
- Zero new dependency (government compliance-friendly supply chain).
- Registry makes adding a notification type a data-only change, never a refactor.

**Areas for Future Enhancement:** bounce-webhook ingestion, outbox archival/pruning, digest taxonomy, MJML re-evaluation, multi-locale variants.

### Implementation Handoff

**AI Agent Guidelines:**
- Follow decisions D1–D8 exactly as documented.
- Route all sends through listener → `SendEmailNotification` orchestrator → `EmailDeliveryService`; never write `email_deliveries` directly.
- Org-scope recipient queries via `scopeForUser()`.
- Use inline theme tokens from `config/email-theme.php` only.

**First Implementation Priority — Foundation Story (brownfield, not a scaffold):**
- `NotificationType` enum
- `NotificationRegistry`
- `email_deliveries` migration (unique `(event_id, user_id, channel)` index)
- `EmailDeliveryService` with `reserve` / `finalize` (+ `markSent` / `markFailed`)
- `users.locale` migration (default `ar`)
- Dedicated `emails` queue connection in `config/queue.php` with `after_commit = true`
