# Development Guide

Coding principles, verification, and commit conventions for working on
Yemen Flow Hub. This is the single source of truth for "how do I verify my
change and commit it" — other docs link here instead of repeating it.

For architecture, see [`architecture/`](architecture/). For how to safely
extend the workflow engine, see [`engine/extension-guide.md`](engine/extension-guide.md).

---

## Repository shape

One Git repository (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`)
tracks everything: `docs/`, `backend/`, `frontend/`, and root configs.
`backend/` and `frontend/` are plain directories, not submodules or nested
repos — commit from the repository root regardless of which directory you
touched.

```bash
git add backend/<files>   # or frontend/<files>, or docs/ AGENTS.md DESIGN.md
git commit -m "type(scope): description"
```

## Tech stack

|         | Backend (`backend/`)         | Frontend (`frontend/`)                        |
| ------- | ---------------------------- | --------------------------------------------- |
| Runtime | PHP 8.2+, Laravel 11         | Nuxt 4, Vue 3.5, TypeScript                   |
| Auth    | Laravel Sanctum              | —                                             |
| Data    | MySQL, Redis (queues, cache) | Pinia, VueUse                                 |
| UI      | REST API, service-oriented   | Tailwind CSS v4, shadcn-vue, VeeValidate, Zod |

Package manager: **pnpm** for all JavaScript tooling. Do not introduce Bun.

Claim validity is **not** a Redis TTL — it's stored directly on
`engine_requests.claim_expires_at` (MySQL), read and enforced by
`App\Services\Workflow\EngineClaimService` against the live
`AdminSettingsService`-backed `support_claim_ttl` setting. See
[`architecture/03-permission-model.md`](architecture/03-permission-model.md)
§4 for the claim mechanism and the TTL-source detail.

---

## Verification ladder

Default verification is **focused**, not exhaustive. Match the check to
the size of the change:

1. Run the smallest relevant test, file, or filter for the touched
   behavior.
2. Run lint/format only for the files you touched, where the tool
   supports scoping to specific files.
3. Run frontend typecheck only when the change touches types, composables,
   stores, API contracts, shared interfaces, or cross-module contracts.
4. Do **not** run the full `pnpm test` or full `php artisan test` suite by
   default.
5. Full suites are for release checks, broad refactors, security-critical
   changes, or when explicitly requested.
6. If a full suite is already known to be red for unrelated reasons,
   report the known baseline and don't treat pre-existing unrelated
   failures as caused by your change.

Focused command examples:

```bash
# Frontend: one Vitest file or a name filter, from frontend/
pnpm exec vitest run app/tests/unit/components/FxConfirmationCard.test.ts
pnpm exec vitest run -t "renders the warning copy"

# Frontend: lint/format specific touched files
pnpm exec eslint app/components/Example.vue app/composables/useExample.ts
pnpm exec prettier app/components/Example.vue --check

# Backend: one PHPUnit file or a filter, from backend/
php artisan test tests/Feature/Auth/PasswordRecoveryTest.php
php artisan test --filter=PasswordRecoveryTest
php artisan test --filter='password reset with valid otp'

# Backend: format specific touched PHP files
vendor/bin/pint app/Services/Workflow/EngineTransitionService.php --test
```

Before editing anything, run `git -c core.fsmonitor=false status --short`
from the repository root and report existing dirty files. Do not modify a
file that's already dirty unless it's directly in scope for your task.

### Full checks (release/broad-refactor only)

| Repo        | Fast check                       | Full check                                                      |
| ----------- | -------------------------------- | --------------------------------------------------------------- |
| `backend/`  | `composer format:check`          | `composer format:check && php artisan test`                     |
| `frontend/` | `pnpm lint && pnpm format:check` | `pnpm lint && pnpm format:check && pnpm typecheck && pnpm test` |

---

## Git hooks

Husky hooks live per-package (`frontend/.husky/`, `backend/.husky/`), not
at the repository root:

| Hook         | `frontend/`                                                   | `backend/`              |
| ------------ | ------------------------------------------------------------- | ----------------------- |
| `pre-commit` | `pnpm exec lint-staged` (staged-file formatting/linting only) | `pnpm exec lint-staged` |
| `commit-msg` | `pnpm exec commitlint --edit "$1"`                            | —                       |
| `pre-push`   | `pnpm lint && pnpm format:check && pnpm typecheck`            | `composer format:check` |

Pre-push hooks intentionally run only non-test gates today — full test
suites are part of the manual/full-check list above, not the hook, until
their existing failures are cleaned up. Frontend lint must pass with
**zero warnings**; do not disable rules broadly to hide old code debt.

A short list of rules stay intentionally disabled, with rationale rather
than as blanket suppressions: Prettier owns void-element formatting
(`vue/html-self-closing`), Vue 3 allows fragments
(`vue/no-multiple-template-root`), TypeScript optional props make
`vue/require-default-prop` noisy, and `@typescript-eslint/no-explicit-any`
is a staged typed-refactor category rather than a hook blocker. Do not
weaken lint, format, or hook rules to make a commit pass — fix the
code/config, or ask how strict the gate should be.

---

## Commit conventions

Format: `type(scope): description` (Conventional Commits), enforced by
Commitlint via the `commit-msg` hook (`frontend/commitlint.config.cjs`,
`backend/commitlint.config.cjs`).

- **Type** — a Conventional Commit type: `feat`, `fix`, `docs`, `style`,
  `refactor`, `test`, `chore`, `build`, `ci`, `perf`, `revert`.
- **Scope** — required. One of: `auth`, `backend`, `docs`, `frontend`,
  `repo`, `settings`, `testing`, `ui`, `workflow`.

Examples: `feat(workflow): add support return validation`,
`fix(frontend): correct bank queue empty state`,
`chore(repo): add lint and format tooling`.

Every AI-assisted commit carries a co-author trailer:

```
Co-Authored-By: Claude <noreply@anthropic.com>
```

All commits stay **signed**. Never use `--no-gpg-sign`, `--no-sign`, or
`-c commit.gpgsign=false`. If signing fails, fix the signing setup — don't
create an unsigned commit as a workaround. Do not bypass Husky hooks with
`--no-verify` unless the user explicitly authorizes an emergency
exception.

Never stage or commit generated artifacts from `graphify-out/` — keep
those local only, even when they change during agent workflows.

---

## Core architecture invariants

These hold across the whole codebase, not just one layer. See
[`architecture/`](architecture/) for the full model each of these belongs
to.

**Never:**

- Mutate `EngineRequest`'s persistence fields (`status`,
  `current_stage_id`) directly on the model — all transitions go through
  `EngineTransitionService::execute()`, which validates stage permissions,
  field rules, and claim ownership before moving an `EngineRequest`. These
  are the database columns; the API-facing names are different —
  `EngineRequestResource` maps `status` → `runtime_status` and the
  `currentStage` relation (keyed by `current_stage_id`) → `current_stage`
  in every JSON response. Don't confuse the persistence field names with
  the API field names when reading code vs. API docs.
- Put business logic in controllers, Vue components, or routes.
- Expose requests outside a user's organization scope — see
  `DataScope` in [`architecture/03-permission-model.md`](architecture/03-permission-model.md).
- Add a per-role dashboard component, or combine `runtime_status`,
  `current_stage`, and `final_outcome` into a single static status enum —
  see `architecture/05-request-state-model.md` and
  `architecture/04-dashboard-architecture.md` (both **planned, not yet
  written**, Step 3; today's authority is AGENTS.md's "Canonical Request
  State Model" and "Dashboard Architecture" sections).
- Compute a "pending work" count from a bespoke per-role query — the
  actionable count, dashboard preview, nav badge, and `/my-queue` all
  resolve through one shared query and must stay equal by record ID.
- Render role-inappropriate UI controls relying on backend rejection
  later — role-forbidden surfaces should not be mounted/rendered at all.
- Replace shadcn-vue components with raw HTML to make a test pass — skip
  or ignore the test instead.

**Always:**

- Enforce organization-scoped visibility at the query level.
- Log every workflow transition to both `workflow_history` and
  `audit_logs`, including `role` at time of action — see the audit
  logging section of [`architecture/03-permission-model.md`](architecture/03-permission-model.md).
- Use `lockForUpdate()` for every workflow transition.
- Validate file type as PDF-only for document uploads.
- Return `REQUEST_CLOSED` (403) for mutations on terminal/inactive
  requests, and the distinct `WORKFLOW_IMMUTABLE_STATE` (409) only for
  editing a published/archived workflow _version_ in the designer.

---

## Browser verification

For UI or frontend changes, start the dev server and exercise the feature
in a real browser before calling the task complete — golden path and edge
cases, watching for regressions elsewhere. Use `playwright-cli` for
browser automation (open a session, `goto`, `snapshot`, interact,
`screenshot` for evidence, `close`). Type checking and test suites verify
code correctness, not feature correctness — if you can't test the UI in a
browser, say so explicitly rather than claiming the feature works.

---

## AI tooling

- **Context7** (`ctx7`) — fetch current library docs before writing
  implementation code that touches Laravel, Nuxt, Vue, Tailwind,
  shadcn-vue, Pinia, VeeValidate, Zod, Sanctum, or Redis. Not for business
  logic or workflow rules — those are covered by this doc set.
- **SocratiCode** — required, not optional, for non-trivial implementation
  work: `codebase_symbol`/`codebase_impact` before modifying an existing
  file, `codebase_search` before writing code that touches existing
  services/models, `codebase_flow` after adding a new public method.
- **graphify** — for codebase questions, prefer `graphify query`/`path`/
  `explain` over raw grep; run `graphify update .` after modifying code
  (local refresh only, never commit `graphify-out/`).
