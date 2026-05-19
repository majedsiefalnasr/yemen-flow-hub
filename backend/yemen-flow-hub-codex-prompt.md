# Yemen Flow Hub — Codex Build Prompt (Backend, Laravel 11)

## How to use this file

Feed Codex **one module at a time**, in order. Do NOT paste all modules at once.
Start each Codex session by pasting the **Global Context** section, then append the module you want it to execute.

---

# 🌍 GLOBAL CONTEXT (always include at the top of every Codex session)

You are working on **Yemen Flow Hub**, an internal regulatory workflow platform for the Central Bank of Yemen (CBY) that manages commercial bank import financing requests.

## Existing project state

- Laravel **11.x** project already initialized.
- `php: ^8.2`, `laravel/framework: ^11.0`.
- **Ignore any existing controllers, models, routes, migrations, or APIs in the project.** Treat the codebase as a clean slate beyond the framework skeleton. If older files conflict with what you generate, delete them.

## Tech stack (fixed — do not deviate)

- Laravel 11 + PHP 8.3+
- MySQL
- Redis (queue + cache)
- Laravel Sanctum (SPA cookie auth **and** Bearer token auth — both must work)
- `darkaonline/l5-swagger` for OpenAPI documentation (annotations-based)
- REST API only — no Blade views except the Swagger UI

## Architecture rules (non-negotiable)

1. **Workflow-first, not CRUD.** Request status changes ONLY through the `WorkflowService`. Controllers never call `$request->update(['status' => ...])` directly.
2. **Service-oriented.** Business logic lives in `app/Services/*`, never in controllers, models, or routes.
3. **Controllers are thin.** Validate (via FormRequest) → call service → return Resource. That's it.
4. **Authorization is centralized** in Policies + workflow rules. Never trust the frontend.
5. **Every workflow action writes** to `request_stage_history` AND `audit_logs`.
6. **Consistent JSON response shape** everywhere:
   ```json
   { "success": true, "message": "...", "data": {...} }
   ```
   Errors:
   ```json
   { "success": false, "message": "...", "errors": {} }
   ```
7. **Multi-tenancy:** Bank users (`DATA_ENTRY`, `BANK_REVIEWER`, `SWIFT_OFFICER`) only see/act on requests belonging to their own `bank_id`. CBY roles (`SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`) see all banks' requests.

## Roles (enum — exact strings)

```
DATA_ENTRY
BANK_REVIEWER
SWIFT_OFFICER
SUPPORT_COMMITTEE
EXECUTIVE_MEMBER
COMMITTEE_DIRECTOR
CBY_ADMIN
```

## Request status (enum — exact strings)

```
DRAFT
SUBMITTED
BANK_APPROVED
BANK_REJECTED
RETURNED_TO_DATA_ENTRY
SUPPORT_APPROVED
SUPPORT_REJECTED
SWIFT_UPLOADED
EXECUTIVE_VOTING
EXECUTIVE_APPROVED
EXECUTIVE_REJECTED
CUSTOMS_ISSUED
COMPLETED
```

## Folder layout (target)

```
app/
├── Actions/
├── DTOs/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/Api/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── Workflow/
│   ├── Voting/
│   ├── Audit/
│   ├── Documents/
│   └── Notifications/
└── Support/
routes/api.php
database/migrations/
database/seeders/
```

## Conventions

- All API routes under `/api` prefix.
- All controllers under `App\Http\Controllers\Api\`.
- All FormRequests under `App\Http\Requests\`.
- All Resources under `App\Http\Resources\`.
- Enums in `App\Enums\` as **PHP 8.1 backed enums** (string-backed).
- No business logic in models — only relationships, casts, scopes.
- Every endpoint must have an `@OA\` Swagger annotation (we'll add them per module).
- Skip tests for now.
- Migrations + seeders only (no factories).

## Output rules for every module

- Print the **list of files created/modified** at the end.
- Print any **`php artisan` or `composer` commands** the user must run.
- Do NOT generate frontend code. Backend only.
- Do NOT touch other modules' files unless explicitly instructed.

---

# 📦 MODULE 1 — Foundation, Enums, Auth (Sanctum dual-mode)

## Goal

Set up the foundation: Sanctum (cookie + token), enums, base response helper, base exception handling, and authentication endpoints.

## Tasks

### 1.1 Install & configure packages

Run:
```bash
composer require laravel/sanctum darkaonline/l5-swagger
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

- Configure `config/sanctum.php` to support **both** SPA (stateful domains via `SANCTUM_STATEFUL_DOMAINS`) and personal access tokens.
- In `bootstrap/app.php`, register Sanctum's `EnsureFrontendRequestsAreStateful` middleware on the `api` group.
- Configure CORS in `config/cors.php` with `supports_credentials => true`.

### 1.2 Create Enums (`app/Enums/`)

- `UserRole` — backed string enum with all 8 canonical role constants from Global Context.
- `RequestStatus` — backed string enum with all 13 status constants.
- `VoteType` — `APPROVE`, `REJECT`, `ABSTAIN`.
- `AuditAction` — at minimum: `LOGIN`, `LOGOUT`, `REQUEST_CREATED`, `REQUEST_UPDATED`, `REQUEST_DELETED`, `STATUS_TRANSITION`, `VOTE_CAST`, `DOCUMENT_UPLOADED`, `DOCUMENT_DOWNLOADED`, `SWIFT_UPLOADED`, `CUSTOMS_ISSUED`.

Each enum should have a `label()` method returning a human-readable Arabic+English label (e.g. `"Data Entry / موظف إدخال البيانات"`).

### 1.3 Base API response helper

Create `app/Support/ApiResponse.php` with static methods:
- `success(mixed $data = null, string $message = 'OK', int $status = 200)`
- `error(string $message, array $errors = [], int $status = 400)`
- `unauthorized(string $message = 'Unauthorized action')`
- `forbidden(string $message = 'Forbidden action')`
- `notFound(string $message = 'Resource not found')`
- `validationError(array $errors)`

All return `JsonResponse` in the standard shape from Global Context.

### 1.4 Exception handling

In `bootstrap/app.php`, register a global exception handler that converts:
- `ValidationException` → `ApiResponse::validationError(...)`
- `AuthenticationException` → `ApiResponse::unauthorized()`
- `AuthorizationException` → `ApiResponse::forbidden()`
- `ModelNotFoundException` / `NotFoundHttpException` → `ApiResponse::notFound()`
- All other uncaught exceptions on `/api/*` routes → `ApiResponse::error(...)` with HTTP 500.

### 1.5 User model + migration

`users` table:
- `id`, `name`, `email` (unique), `password`
- `role` (enum string, indexed)
- `bank_id` (nullable FK → `banks.id`, indexed) — null for CBY roles
- `is_active` (bool, default true)
- `last_login_at` (nullable timestamp)
- `remember_token`, `timestamps`

`User` model:
- Casts `role` to `UserRole` enum.
- Relationships: `bank()`, `votes()`, `auditLogs()`.
- Helper methods: `isBankUser(): bool`, `isCbyUser(): bool`, `hasRole(UserRole $role): bool`.
- Uses `HasApiTokens`, `Notifiable`.

### 1.6 Auth controller + endpoints

`App\Http\Controllers\Api\AuthController`:

| Method | Endpoint | Behavior |
|---|---|---|
| POST | `/api/auth/login` | Validate email+password. If SPA request (has session) → regenerate session, no token. If pure API → return Sanctum token. Log `LOGIN` audit. |
| POST | `/api/auth/logout` | Invalidate session AND revoke current token. Log `LOGOUT` audit. |
| GET | `/api/auth/me` | Return current user + role + bank (if any). |

Use `LoginRequest` FormRequest for validation.

Return user via `UserResource` (create it: `id, name, email, role, role_label, bank_id, bank_name, is_active`).

### 1.7 Swagger setup

- Configure `config/l5-swagger.php` with title `"Yemen Flow Hub API"`, version `"1.0.0"`.
- Create `app/Http/Controllers/Api/Controller.php` base class with the `@OA\Info`, `@OA\Server`, and `@OA\SecurityScheme` (both `sanctum` cookie and `bearerAuth` token) root annotations.
- All future API controllers extend this base.
- Add Swagger annotations to all 3 auth endpoints.
- Verify `/api/documentation` route serves the Swagger UI.

### 1.8 Routes

In `routes/api.php`:
```php
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
```

## Deliverable

At end of module, print:
- File tree of everything created.
- Commands to run: `composer install`, `php artisan migrate`, `php artisan l5-swagger:generate`.

---

# 📦 MODULE 2 — Banks & Users Management

## Goal

CRUD for banks and users, restricted to `CBY_ADMIN`.

## Tasks

### 2.1 Bank model + migration

`banks` table:
- `id`, `name` (unique), `code` (unique, short identifier like `CAC`, `YCB`), `is_active` (bool), `timestamps`.

`Bank` model:
- Relationships: `users()`, `importRequests()`.
- Scope: `scopeActive()`.

### 2.2 BankPolicy

- `viewAny`, `view`: any authenticated user.
- `create`, `update`, `delete`: only `CBY_ADMIN`.

### 2.3 Bank controller + routes

`App\Http\Controllers\Api\BankController`:

| Method | Endpoint |
|---|---|
| GET | `/api/banks` |
| POST | `/api/banks` |
| GET | `/api/banks/{id}` |
| PUT | `/api/banks/{id}` |
| DELETE | `/api/banks/{id}` |

Use `StoreBankRequest`, `UpdateBankRequest`. Use `BankResource`.

### 2.4 User management

`App\Http\Controllers\Api\UserController`:

| Method | Endpoint |
|---|---|
| GET | `/api/users` (filterable by `role`, `bank_id`, `is_active`) |
| POST | `/api/users` |
| GET | `/api/users/{id}` |
| PUT | `/api/users/{id}` |
| DELETE | `/api/users/{id}` (soft via `is_active = false`) |

`UserPolicy`:
- All actions: only `CBY_ADMIN`.
- Validation rule: if `role` is a bank role, `bank_id` is required. If `role` is a CBY role, `bank_id` must be null.

`StoreUserRequest`, `UpdateUserRequest`:
- Validate role is valid enum.
- Enforce bank_id + role consistency rule above.
- Password required on create, optional on update.

### 2.5 Swagger annotations

Annotate every endpoint in this module.

### 2.6 Seeder

Create `DatabaseSeeder` that calls:
- `BankSeeder` — 3 sample banks.
- `UserSeeder` — 1 user per role:
  - 1 `CBY_ADMIN` (admin@cby.gov.ye / password)
  - 1 `COMMITTEE_DIRECTOR`
  - 6 `EXECUTIVE_MEMBER`s
  - 2 `SUPPORT_COMMITTEE` members
  - For each of the 3 banks: 1 `DATA_ENTRY`, 1 `BANK_REVIEWER`, 1 `SWIFT_OFFICER`.

All users get password `password` (bcrypt).

## Deliverable

File tree + run instructions: `php artisan migrate:fresh --seed && php artisan l5-swagger:generate`.

---

# 📦 MODULE 3 — Import Request Model & CRUD

## Goal

Build the `ImportRequest` model, migration, and DRAFT-stage CRUD endpoints. **No workflow transitions yet** — only operations valid in `DRAFT` and `RETURNED_TO_DATA_ENTRY` states.

## Tasks

### 3.1 Migrations

`import_requests` table:
- `id`, `reference_number` (unique, format `YFH-{YYYY}-{6-digit-sequence}`, generated on create)
- `bank_id` (FK, indexed)
- `created_by` (FK → users.id)
- `currency` (string, 3 chars, e.g. `USD`)
- `amount` (decimal 18,2)
- `supplier_name` (string)
- `goods_description` (text)
- `port_of_entry` (string)
- `notes` (text, nullable)
- `status` (enum string, default `DRAFT`, indexed)
- `current_owner_role` (enum string, indexed) — who owns the request now
- `submitted_at`, `bank_approved_at`, `support_approved_at`, `swift_uploaded_at`, `executive_decided_at`, `customs_issued_at` (all nullable timestamps)
- `revision_count` (unsigned int, default 0) — incremented each time it's returned to data entry
- `timestamps`, `softDeletes`

`request_documents` table:
- `id`, `request_id` (FK, indexed), `uploaded_by` (FK), `type` (enum: `REQUEST_DOC`, `SWIFT`, `CUSTOMS`), `original_filename`, `stored_path`, `mime_type`, `size_bytes`, `timestamps`.

(Other tables — `request_stage_history`, `request_votes`, `audit_logs`, `customs_declarations` — come in later modules.)

### 3.2 ImportRequest model

- Casts: `status` → `RequestStatus`, `current_owner_role` → `UserRole`, amount → decimal, all timestamp fields.
- Relationships: `bank()`, `creator()`, `documents()`, `votes()` (placeholder), `stageHistory()` (placeholder), `customsDeclaration()` (placeholder).
- Scopes:
  - `scopeForUser(User $user)` — applies bank scoping: if `$user->isBankUser()`, restrict to `where('bank_id', $user->bank_id)`. Else no restriction.
  - `scopeStatus(RequestStatus|array $status)`.
- Helper: `isEditable(): bool` — true only in `DRAFT` or `RETURNED_TO_DATA_ENTRY`.
- Auto-generate `reference_number` in a `creating` model event.

### 3.3 ImportRequestPolicy

- `viewAny`: any authenticated user.
- `view`: bank users only see their bank's requests; CBY users see all.
- `create`: only `DATA_ENTRY`.
- `update`: only `DATA_ENTRY` from same bank AND request `isEditable()`.
- `delete`: only `DATA_ENTRY` from same bank AND status === `DRAFT`.

### 3.4 ImportRequestController

`App\Http\Controllers\Api\ImportRequestController`:

| Method | Endpoint |
|---|---|
| GET | `/api/requests` — list, paginated, filterable by `status`, `bank_id` (CBY only), `search` (reference / supplier), `from_date`, `to_date` |
| POST | `/api/requests` — create in DRAFT |
| GET | `/api/requests/{id}` — details |
| PUT | `/api/requests/{id}` — update (only when editable) |
| DELETE | `/api/requests/{id}` — delete (only DRAFT) |

Resources: `ImportRequestResource` (full), `ImportRequestListResource` (lighter for list view).

FormRequests: `StoreImportRequest`, `UpdateImportRequest` with validation:
- `currency`: required, 3 chars, in [`USD`, `EUR`, `SAR`, `AED`, `CNY`].
- `amount`: required, numeric, min 1.
- `supplier_name`, `goods_description`, `port_of_entry`: required strings.
- `notes`: optional.

### 3.5 Bank-scoping guard

Apply scoping in the controller `index()` via `ImportRequest::forUser($request->user())`.

### 3.6 Swagger annotations on all 5 endpoints

## Deliverable

File tree + `php artisan migrate && php artisan l5-swagger:generate`.

---

# 📦 MODULE 4 — Workflow Service (the heart of the system)

## Goal

Build the centralized `WorkflowService` that handles ALL status transitions, owner transfers, locking, and stage history. After this module, no other code is allowed to change `import_requests.status` directly.

## Tasks

### 4.1 Migration: `request_stage_history`

Columns:
- `id`, `request_id` (FK, indexed)
- `from_status` (enum string, nullable for initial)
- `to_status` (enum string)
- `from_owner_role`, `to_owner_role` (enum strings, nullable)
- `actor_id` (FK → users.id)
- `actor_role` (enum string)
- `action` (string, e.g. `submit`, `bank_approve`, `support_reject`)
- `reason` (text, nullable — used for rejections / returns)
- `metadata` (json, nullable)
- `timestamps`

### 4.2 Transition map (single source of truth)

Create `app/Services/Workflow/TransitionMap.php` defining a static array:

```php
// action => [from_status[], to_status, required_role, new_owner_role]
'submit'             => [[DRAFT, RETURNED_TO_DATA_ENTRY], SUBMITTED,             DATA_ENTRY,         BANK_REVIEWER]
'bank_approve'       => [[SUBMITTED],                     BANK_APPROVED,         BANK_REVIEWER,      SUPPORT_COMMITTEE]
'bank_reject'        => [[SUBMITTED],                     BANK_REJECTED,         BANK_REVIEWER,      null]
'return_to_entry'    => [[SUBMITTED, SUPPORT_REJECTED, EXECUTIVE_REJECTED], RETURNED_TO_DATA_ENTRY, BANK_REVIEWER, DATA_ENTRY]
'support_approve'    => [[BANK_APPROVED],                 SUPPORT_APPROVED,      SUPPORT_COMMITTEE,  SWIFT_OFFICER]
'support_reject'     => [[BANK_APPROVED],                 SUPPORT_REJECTED,      SUPPORT_COMMITTEE,  BANK_REVIEWER]
'swift_upload'       => [[SUPPORT_APPROVED],              SWIFT_UPLOADED,        SWIFT_OFFICER,      EXECUTIVE_MEMBER]
'start_voting'       => [[SWIFT_UPLOADED],                EXECUTIVE_VOTING,      EXECUTIVE_MEMBER,   EXECUTIVE_MEMBER]
'finalize_approved'  => [[EXECUTIVE_VOTING],              EXECUTIVE_APPROVED,    COMMITTEE_DIRECTOR, CBY_ADMIN]   // also entered by voting engine
'finalize_rejected'  => [[EXECUTIVE_VOTING],              EXECUTIVE_REJECTED,    COMMITTEE_DIRECTOR, BANK_REVIEWER]
'issue_customs'      => [[EXECUTIVE_APPROVED],            CUSTOMS_ISSUED,        CBY_ADMIN,          CBY_ADMIN]
'complete'           => [[CUSTOMS_ISSUED],                COMPLETED,             CBY_ADMIN,          null]
```

(Use the actual enum cases, not strings, in the PHP code.)

### 4.3 WorkflowService

`app/Services/Workflow/WorkflowService.php`:

```php
public function transition(
    ImportRequest $request,
    string $action,
    User $actor,
    ?string $reason = null,
    array $metadata = []
): ImportRequest
```

Logic:
1. Look up `$action` in `TransitionMap`. Throw `InvalidTransitionException` if unknown.
2. Verify `$request->status` is in the allowed `from_status` list. Else throw `InvalidTransitionException`.
3. Verify `$actor->role` matches `required_role`. Else throw `UnauthorizedTransitionException`.
4. If bank user, verify `$actor->bank_id === $request->bank_id`. Else `UnauthorizedTransitionException`.
5. **Critical rule:** for `bank_approve`, verify `$actor->id !== $request->created_by` (separation of duties). Throw `SelfReviewException` if violated.
6. Wrap in DB transaction:
   - Update `status`, `current_owner_role`.
   - Update relevant timestamp column (`submitted_at`, `bank_approved_at`, etc.) based on action.
   - If action is `return_to_entry`, increment `revision_count`.
   - Insert `request_stage_history` record.
   - Insert `audit_logs` record (call `AuditService` — stub it for now, full impl in Module 8).
   - Fire event `RequestTransitioned`.
7. Return refreshed model.

Custom exceptions in `app/Exceptions/`:
- `InvalidTransitionException` (HTTP 422)
- `UnauthorizedTransitionException` (HTTP 403)
- `SelfReviewException` (HTTP 403)

Register them in the exception handler with proper `ApiResponse` mappings.

### 4.4 WorkflowController + endpoints

`App\Http\Controllers\Api\WorkflowController`:

| Method | Endpoint | Action |
|---|---|---|
| POST | `/api/workflow/{id}/submit` | submit |
| POST | `/api/workflow/{id}/bank-approve` | bank_approve |
| POST | `/api/workflow/{id}/bank-reject` | bank_reject |
| POST | `/api/workflow/{id}/return-to-entry` | return_to_entry |
| POST | `/api/workflow/{id}/support-approve` | support_approve |
| POST | `/api/workflow/{id}/support-reject` | support_reject |
| POST | `/api/workflow/{id}/finalize-decision` | finalize_approved OR finalize_rejected (determined by voting result — handled by VotingService in Module 5; for now expose endpoint that calls service) |

Each endpoint:
- Loads the request (via route model binding).
- Reads optional `reason` from request body (required for rejections + returns).
- Calls `WorkflowService::transition()`.
- Returns updated `ImportRequestResource`.

`WorkflowActionRequest` FormRequest validates `reason` based on action.

### 4.5 Request history endpoint

`GET /api/requests/{id}/history` — returns the `request_stage_history` records for a request via `StageHistoryResource`.

### 4.6 Swagger annotations on all endpoints

## Deliverable

File tree + `php artisan migrate && php artisan l5-swagger:generate`.

---

# 📦 MODULE 5 — Voting Service

## Goal

Executive Committee voting: 6 members, one vote each, majority logic, tie-breaker via Committee Director.

## Tasks

### 5.1 Migration: `request_votes`

Columns:
- `id`, `request_id` (FK, indexed), `user_id` (FK, indexed)
- `vote` (enum: `APPROVE`, `REJECT`, `ABSTAIN`)
- `justification` (text, nullable)
- `is_director_override` (bool, default false) — true if this row is the director's tie-breaker
- `timestamps`
- Unique constraint on `(request_id, user_id)` to prevent duplicate votes.

### 5.2 VotingService

`app/Services/Voting/VotingService.php`:

```php
public function castVote(ImportRequest $request, User $voter, VoteType $vote, ?string $justification): RequestVote
public function tally(ImportRequest $request): VotingTally   // DTO with approve/reject/abstain counts + result
public function finalize(ImportRequest $request, User $director, ?VoteType $directorVote = null): ImportRequest
```

**castVote logic:**
- Verify `$request->status === EXECUTIVE_VOTING`. Else throw.
- Verify `$voter->role === EXECUTIVE_MEMBER`. Else throw.
- Verify no existing vote for this `(request, voter)` pair. Else throw `DuplicateVoteException`.
- Insert vote row.
- Insert audit log (`VOTE_CAST`).
- After insert, call `tally()`. If `approve >= 4` → auto-finalize approved. If `reject >= 4` → auto-finalize rejected. (Auto-finalize calls `WorkflowService::transition()` with `finalize_approved` or `finalize_rejected`, using a special "system" actor — actually use the last voter as actor with override flag in metadata).
- Return the vote.

**tally logic:**
- Count votes by type.
- Return DTO with: `approveCount`, `rejectCount`, `abstainCount`, `totalCast`, `isDecided` (bool), `result` (APPROVED / REJECTED / TIE / PENDING).
- Decision rules:
  - approve >= 4 → APPROVED
  - reject >= 4 → REJECTED
  - totalCast === 6 and neither side >= 4 → TIE
  - else PENDING

**finalize logic (director tie-breaker):**
- Verify `$director->role === COMMITTEE_DIRECTOR`.
- Verify current tally is TIE.
- Insert a `request_votes` row for the director with `is_director_override = true`.
- Call `WorkflowService::transition()` with `finalize_approved` or `finalize_rejected` based on director's vote.

### 5.3 Voting endpoints

`App\Http\Controllers\Api\VotingController`:

| Method | Endpoint | Role |
|---|---|---|
| GET | `/api/voting` | List requests currently in `EXECUTIVE_VOTING` (executive members + director see all; others 403) |
| GET | `/api/voting/{id}` | Voting detail: request info + current tally + my vote (if any) |
| POST | `/api/voting/{id}/vote` | Cast vote (executive member) |
| POST | `/api/voting/{id}/director-decide` | Director tie-breaker — body: `{ vote: APPROVE|REJECT, justification }` |

FormRequests: `CastVoteRequest`, `DirectorDecideRequest`.
Resources: `VoteResource`, `VotingTallyResource`.

### 5.4 Auto-start voting

When `WorkflowService` transitions a request to `SWIFT_UPLOADED`, immediately also transition to `EXECUTIVE_VOTING` (auto-chain via event listener on `RequestTransitioned`, or inline). Decision: do it **inline** inside the `swift_upload` action so the request lands directly in `EXECUTIVE_VOTING`.

### 5.5 Swagger annotations

## Deliverable

File tree + migrate + swagger generate.

---

# 📦 MODULE 6 — Document Service (uploads, SWIFT, downloads)

## Goal

Secure file upload/download for request documents and SWIFT files. Private storage. Access control on download.

## Tasks

### 6.1 DocumentService

`app/Services/Documents/DocumentService.php`:

```php
public function uploadRequestDocument(ImportRequest $request, User $uploader, UploadedFile $file): RequestDocument
public function uploadSwift(ImportRequest $request, User $uploader, UploadedFile $file): RequestDocument
public function download(RequestDocument $document, User $user): StreamedResponse
public function delete(RequestDocument $document, User $user): void
```

**Validation:**
- Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
- Max size: 10 MB (configurable via `config/documents.php`).

**Storage:**
- Use the `local` disk, root `storage/app/private/`.
- Path structure:
  - Request docs: `requests/{request_id}/{uuid}.{ext}`
  - SWIFT: `swift/{request_id}/{uuid}.{ext}`
  - Customs: `customs/{request_id}/{uuid}.{ext}` (Module 7)
- Store the relative path, never expose absolute paths.

**Rules:**
- `uploadRequestDocument`: only when request `isEditable()`. Only by request creator's bank's `DATA_ENTRY`.
- `uploadSwift`: only when status is `SUPPORT_APPROVED`. Only by `SWIFT_OFFICER` of same bank. After success, calls `WorkflowService::transition($request, 'swift_upload', $user)`.
- SWIFT files **cannot be replaced or deleted** after upload — enforce in service.
- `delete`: only for `REQUEST_DOC` type, only when request is editable.
- `download`: verify the user has permission to view the parent request (via `ImportRequestPolicy::view`).

All operations log to `AuditService`.

### 6.2 DocumentController endpoints

| Method | Endpoint | Notes |
|---|---|---|
| POST | `/api/requests/{id}/documents` | Upload request document (multipart) |
| DELETE | `/api/documents/{id}` | Delete request doc (only when editable) |
| POST | `/api/workflow/{id}/swift-upload` | Upload SWIFT (multipart) — triggers transition |
| GET | `/api/documents/{id}/download` | Stream file |

FormRequests: `UploadDocumentRequest`, `UploadSwiftRequest`.
Resource: `DocumentResource` (id, type, original_filename, mime_type, size_bytes, uploaded_at, download_url).

### 6.3 Swagger annotations

Mark multipart endpoints with `@OA\MediaType(mediaType="multipart/form-data")`.

## Deliverable

File tree + run instructions.

---

# 📦 MODULE 7 — Customs Declaration

## Goal

CBY Admin generates the final customs declaration document and PDF.

## Tasks

### 7.1 Migration: `customs_declarations`

Columns:
- `id`, `request_id` (FK, unique — one per request)
- `declaration_number` (unique, format `CD-{YYYY}-{6-digit}`)
- `issued_by` (FK → users.id)
- `issued_at` (timestamp)
- `pdf_path` (string)
- `metadata` (json — snapshot of request data at issuance time)
- `timestamps`

### 7.2 CustomsService

`app/Services/Customs/CustomsService.php`:

```php
public function generate(ImportRequest $request, User $issuer): CustomsDeclaration
public function getPdfStream(CustomsDeclaration $declaration, User $user): StreamedResponse
```

**generate logic:**
- Verify `$request->status === EXECUTIVE_APPROVED`.
- Verify `$issuer->role === CBY_ADMIN`.
- Auto-generate `declaration_number`.
- Render PDF (use `barryvdh/laravel-dompdf` — install: `composer require barryvdh/laravel-dompdf`).
- Blade template `resources/views/pdf/customs-declaration.blade.php` — **RTL Arabic layout**, includes:
  - Header: Central Bank of Yemen logo placeholder + Arabic title
  - Declaration number, issue date
  - Bank info, supplier info, amount, currency
  - Goods description, port of entry
  - Approval references (bank approval date, support approval date, executive decision date)
  - Signature blocks
- Save PDF to `storage/app/private/customs/{request_id}/{declaration_number}.pdf`.
- Snapshot request data into `metadata` JSON.
- Insert customs_declaration row.
- Call `WorkflowService::transition($request, 'issue_customs', $issuer)` then `complete`.
- Log audit (`CUSTOMS_ISSUED`).

### 7.3 CustomsController endpoints

| Method | Endpoint |
|---|---|
| POST | `/api/customs/{request_id}/generate` |
| GET | `/api/customs/{id}` (returns declaration metadata) |
| GET | `/api/customs/{id}/download` (PDF stream) |

Resource: `CustomsDeclarationResource`.

### 7.4 Swagger annotations

## Deliverable

File tree + composer require dompdf + migrate + swagger generate.

---

# 📦 MODULE 8 — Audit Service & Notifications

## Goal

Wire up the audit logging system (referenced as stubs in earlier modules) and basic notifications via Laravel's notification system.

## Tasks

### 8.1 Migration: `audit_logs`

Columns:
- `id`, `user_id` (FK, nullable for system events)
- `user_role` (enum string, nullable)
- `action` (string — values from `AuditAction` enum)
- `subject_type` (string, nullable — e.g. `App\Models\ImportRequest`)
- `subject_id` (unsigned big int, nullable)
- `ip_address` (string, nullable)
- `user_agent` (string, nullable)
- `metadata` (json, nullable)
- `created_at` (single timestamp — audit logs are append-only)

Index `(subject_type, subject_id)` and `(action)`.

### 8.2 AuditService

`app/Services/Audit/AuditService.php`:

```php
public function log(AuditAction $action, ?User $actor, ?Model $subject = null, array $metadata = []): AuditLog
```

- Auto-capture `ip_address` and `user_agent` from current request.
- Insert one row, return it.
- Replace all `AuditService` stubs in earlier modules to call this real implementation.

### 8.3 Audit endpoints

`App\Http\Controllers\Api\AuditController`:

| Method | Endpoint | Role |
|---|---|---|
| GET | `/api/audit` | List audit logs, filterable by `user_id`, `action`, `subject_type`, `from_date`, `to_date`. Only `CBY_ADMIN` and `COMMITTEE_DIRECTOR`. |

Resource: `AuditLogResource`.

### 8.4 Notifications

Create notification classes in `app/Notifications/`:
- `RequestSubmittedNotification` — to bank reviewers of the same bank.
- `RequestApprovedNotification` — to creator.
- `RequestRejectedNotification` — to creator + reviewer.
- `RequestReturnedNotification` — to data entry.
- `SwiftUploadRequestedNotification` — to SWIFT officers of the same bank when status hits `SUPPORT_APPROVED`.
- `VotingOpenedNotification` — to all executive members + director.
- `CustomsIssuedNotification` — to creator + bank reviewer.

Each implements `ShouldQueue`, uses `database` channel (add `notifications` table migration via `php artisan notifications:table`).

Hook them via an event listener `SendWorkflowNotifications` listening to `RequestTransitioned` — dispatches the right notification based on the new status.

### 8.5 Notification endpoints

| Method | Endpoint |
|---|---|
| GET | `/api/notifications` (current user's notifications, paginated) |
| POST | `/api/notifications/{id}/read` (mark as read) |
| POST | `/api/notifications/read-all` |

### 8.6 Swagger annotations

## Deliverable

File tree + migrate + swagger generate.

---

# 📦 MODULE 9 — Dashboard, Reports, Final Swagger Polish

## Goal

Dashboard stats endpoints, basic reports, and a final pass to ensure the Swagger documentation is complete, correct, and groups endpoints by tag.

## Tasks

### 9.1 Dashboard controller

`App\Http\Controllers\Api\DashboardController`:

| Method | Endpoint |
|---|---|
| GET | `/api/dashboard/stats` |

Response (scoped by user role — bank users see only their bank):
```json
{
  "total_requests": 0,
  "by_status": { "DRAFT": 0, "SUBMITTED": 0, ... },
  "pending_action_for_me": 0,
  "this_month": { "created": 0, "approved": 0, "rejected": 0 },
  "voting": { "open_for_me": 0, "ties_pending_director": 0 }
}
```

`pending_action_for_me` = count of requests where `current_owner_role === $user->role` (and bank-scoped if applicable).

### 9.2 Reports controller

| Method | Endpoint | Role |
|---|---|---|
| GET | `/api/reports/workflow` | Aggregated workflow metrics: avg time-per-stage, throughput. CBY only. |
| GET | `/api/reports/voting` | Voting stats: approval rate, tie rate, avg time to decision. CBY only. |

Simple JSON responses are fine — keep it lightweight.

### 9.3 Final Swagger polish

- Verify every endpoint has full `@OA` annotations: summary, description, tags, parameters, request body schema, response schemas (200, 401, 403, 404, 422).
- Group endpoints by tag: `Auth`, `Users`, `Banks`, `Requests`, `Workflow`, `Voting`, `Documents`, `Customs`, `Audit`, `Notifications`, `Dashboard`, `Reports`.
- Define reusable schemas in the base controller for: `ImportRequest`, `User`, `Bank`, `Vote`, `Document`, `CustomsDeclaration`, `ApiSuccess`, `ApiError`, `ValidationError`.
- Confirm both auth schemes (`sanctum` cookie + `bearerAuth` token) are wired correctly.
- Run `php artisan l5-swagger:generate` and verify `/api/documentation` renders without errors.

### 9.4 README updates

Append to `README.md`:
- Project description (1 paragraph).
- Setup steps: `composer install`, `cp .env.example .env`, configure DB, `php artisan key:generate`, `php artisan migrate --seed`, `php artisan l5-swagger:generate`, `php artisan serve`.
- Default seeded credentials.
- Link to Swagger docs: `http://localhost:8000/api/documentation`.
- Brief workflow diagram (ASCII).

## Deliverable

Final file tree + final run instructions + confirmation that all 13 statuses, all 12 transitions, and all endpoints from `06-api-reference.md` are implemented.

---

# ✅ Acceptance Checklist (run after Module 9)

- [ ] All endpoints from `06-api-reference.md` exist and are documented in Swagger.
- [ ] No controller or model contains workflow logic.
- [ ] All status changes go through `WorkflowService::transition()`.
- [ ] Bank users cannot see/act on other banks' requests.
- [ ] Same user cannot create + bank-approve the same request.
- [ ] Voting auto-finalizes at 4 approvals or 4 rejections.
- [ ] Director can break ties.
- [ ] SWIFT file cannot be replaced after upload.
- [ ] Every transition writes to `request_stage_history` and `audit_logs`.
- [ ] Both Sanctum cookie auth and Bearer token auth work.
- [ ] `php artisan migrate:fresh --seed` runs cleanly.
- [ ] `php artisan l5-swagger:generate` produces valid spec.
