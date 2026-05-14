# Yemen Flow Hub — Update Prompt #1 (Major Refactor)

## How to use

These are **patch instructions** for an existing Laravel 11 codebase (Modules 1–9 + seeder + test page already built). Codex must:
- **Read existing files first** before editing.
- **Modify in place** — don't recreate working code.
- **Preserve existing Swagger annotations** and extend them, never replace.

Feed Codex **one chunk at a time** in order. Each chunk has its own acceptance checklist.

---

# 🌍 Context summary for every chunk

Add this preamble at the start of every Codex session for this update:

> You are updating the **Yemen Flow Hub** Laravel 11 backend based on new client feedback derived from the live prototype at `yemen-flow-hub.lovable.app`. The original implementation (Modules 1–9) used 7 roles including `COMMITTEE_DIRECTOR`. The prototype now defines **exactly 8 roles** — `COMMITTEE_DIRECTOR` is removed entirely, and two new roles are added: `BANK_MANAGER` (commercial bank supervisor) and `EXECUTIVE_DIRECTOR` (head of the executive committee). The update also introduces a Merchants entity, a Permissions matrix table, tighter editing rules, and a claim mechanism for the Support Committee. Read the existing code in `app/Enums/`, `app/Services/Workflow/`, `app/Models/`, `app/Http/Controllers/Api/`, and `database/migrations/` before editing. Preserve all existing Swagger `@OA` annotations and extend them — never delete them.

---

# 📦 CHUNK 1 — Roles, Permissions Matrix, Merchants, Document Types

## Goal

Restructure the role enum to match the prototype (**exactly 8 roles**, with `COMMITTEE_DIRECTOR` fully removed and `BANK_MANAGER` + `EXECUTIVE_DIRECTOR` added), introduce a database-driven permissions matrix, add the `merchants` table, and add the `document_types` table.

---

## Task 1.1 — Update `UserRole` enum

Open `app/Enums/UserRole.php`. Final state must contain exactly these **8 cases** (string-backed), matching the production prototype at `yemen-flow-hub.lovable.app`:

```php
case CBY_ADMIN          = 'CBY_ADMIN';
case BANK_MANAGER       = 'BANK_MANAGER';        // NEW
case DATA_ENTRY         = 'DATA_ENTRY';
case BANK_REVIEWER      = 'BANK_REVIEWER';
case SWIFT_OFFICER      = 'SWIFT_OFFICER';
case SUPPORT_COMMITTEE  = 'SUPPORT_COMMITTEE';
case EXECUTIVE_MEMBER   = 'EXECUTIVE_MEMBER';
case EXECUTIVE_DIRECTOR = 'EXECUTIVE_DIRECTOR';  // NEW
```

Arabic labels in `label()` (these match the exact labels used in the prototype's login page):
```
CBY_ADMIN          => 'مسؤول النظام (CBY) / CBY Admin'
BANK_MANAGER       => 'مسؤول البنك / Bank Manager'
DATA_ENTRY         => 'موظف إدخال البنك / Bank Data Entry'
BANK_REVIEWER      => 'مراجع داخلي بالبنك / Bank Internal Reviewer'
SWIFT_OFFICER      => 'موظف السويفت بالبنك / Bank SWIFT Officer'
SUPPORT_COMMITTEE  => 'عضو اللجنة المساندة / Support Committee Member'
EXECUTIVE_MEMBER   => 'عضو اللجنة التنفيذية / Executive Committee Member'
EXECUTIVE_DIRECTOR => 'مدير اللجنة التنفيذية / Executive Committee Director'
```

Add helpers:
```php
public function isBankRole(): bool   // DATA_ENTRY, BANK_REVIEWER, SWIFT_OFFICER, BANK_MANAGER
public function isCbyRole(): bool    // CBY_ADMIN, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER, EXECUTIVE_DIRECTOR
```

## ⚠️ Important — REMOVE `COMMITTEE_DIRECTOR` completely

The original Module 1 had a `COMMITTEE_DIRECTOR` role. This role does NOT exist in the production prototype and must be **fully removed** from the system:

1. Remove the case from the `UserRole` enum.
2. Remove its label() entry.
3. Search the entire codebase for `COMMITTEE_DIRECTOR` and remove every reference:
   - `app/Services/Workflow/TransitionMap.php` — replace all uses with `EXECUTIVE_DIRECTOR`.
   - `app/Services/Voting/VotingService.php` — `finalize()` method now requires `EXECUTIVE_DIRECTOR`.
   - `app/Http/Controllers/Api/VotingController.php` — `director-decide` endpoint is now for `EXECUTIVE_DIRECTOR`.
   - `app/Policies/*` — any role check.
   - `database/seeders/UserSeeder.php` — the committee_director user must be deleted (this is handled in Chunk 4, but flag any current references).
   - `database/seeders/Support/RequestScenarioBuilder.php` — any `COMMITTEE_DIRECTOR` actor is now `EXECUTIVE_DIRECTOR`.

After this cleanup, the codebase should have ZERO occurrences of the string `COMMITTEE_DIRECTOR` (other than maybe historical comments — delete those too).

**Sanity check command** Codex should run before declaring Chunk 1 done:
```bash
grep -rn "COMMITTEE_DIRECTOR" app/ database/ routes/ config/
```
Expected output: no matches.

---

## Task 1.2 — Migration: `permissions` and `role_permissions` tables

Create two migrations:

```bash
php artisan make:migration create_permissions_table
php artisan make:migration create_role_permissions_table
```

### `permissions` table
```php
$table->id();
$table->string('slug')->unique();              // e.g. 'request.create'
$table->string('name_ar');                     // 'إنشاء طلب تمويل'
$table->string('name_en');                     // 'Create financing request'
$table->string('group')->index();              // 'requests', 'voting', 'admin', etc.
$table->timestamps();
```

### `role_permissions` table
```php
$table->id();
$table->string('role');                        // matches UserRole enum value
$table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
$table->timestamps();
$table->unique(['role', 'permission_id']);
$table->index('role');
```

---

## Task 1.3 — Permission seeder (the 16 permissions matrix)

Create `database/seeders/PermissionSeeder.php` and register it in `DatabaseSeeder` **before** `UserSeeder`.

Seed exactly these 16 permissions:

| slug | name_ar | name_en | group |
|---|---|---|---|
| `request.create` | إنشاء طلب تمويل | Create financing request | requests |
| `request.review` | مراجعة الطلبات | Review requests | requests |
| `request.approve` | اعتماد الطلبات | Approve requests | requests |
| `request.reject` | رفض الطلبات | Reject requests | requests |
| `swift.upload` | رفع وثيقة السويفت | Upload SWIFT document | swift |
| `voting.cast` | التصويت على الطلبات | Cast vote on requests | voting |
| `voting.finalize` | إغلاق التصويت ونشر القرار | Finalize voting and publish decision | voting |
| `customs.issue` | إصدار البيان الجمركي | Issue customs declaration | customs |
| `reports.view` | عرض التقارير | View reports | analytics |
| `audit.view` | عرض سجل التدقيق | View audit log | analytics |
| `merchants.manage` | إدارة التجار | Manage merchants | admin |
| `users.manage` | إدارة المستخدمين | Manage users | admin |
| `entities.manage` | إدارة البنوك والصرافات | Manage banks | admin |
| `docrules.manage` | إدارة قواعد المستندات | Manage document types | admin |
| `roles.manage` | إدارة الأدوار والصلاحيات | Manage roles and permissions | admin |
| `request.claim` | حجز الطلب للمراجعة | Claim request for review | requests |

Then in the same seeder, populate `role_permissions` from this exact matrix (**8 roles**, matching the prototype):

| Permission | CBY_ADMIN | BANK_MANAGER | DATA_ENTRY | BANK_REVIEWER | SWIFT_OFFICER | SUPPORT_COMMITTEE | EXECUTIVE_MEMBER | EXECUTIVE_DIRECTOR |
|---|---|---|---|---|---|---|---|---|
| request.create        | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| request.review        | ✗ | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| request.approve       | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ |
| request.reject        | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ |
| request.claim         | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ |
| swift.upload          | ✗ | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ |
| voting.cast           | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ | ✓ |
| voting.finalize       | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| customs.issue         | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| reports.view          | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✓ | ✓ |
| audit.view            | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ | ✓ | ✓ |
| merchants.manage      | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| users.manage          | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| entities.manage       | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| docrules.manage       | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| roles.manage          | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

**Note:** `BANK_MANAGER` is essentially a power user inside a bank — can create, review, upload SWIFT, manage their bank's users and merchants.

---

## Task 1.4 — `Permission` model and `PermissionService`

### Model `app/Models/Permission.php`
- Fillable: `slug`, `name_ar`, `name_en`, `group`.
- Relationship: `roles()` via `role_permissions` (returns distinct role strings).

### Service `app/Services/Authorization/PermissionService.php`

```php
public function userCan(User $user, string $permissionSlug): bool
public function permissionsForRole(UserRole $role): Collection
public function rolesForPermission(string $permissionSlug): array
```

`userCan()` reads from `role_permissions` (cached for 1 hour via `Cache::remember()` keyed by role). Cache is invalidated whenever role permissions are updated.

Bind this service in `AppServiceProvider`. Add a `Gate::before()` hook in `AuthServiceProvider` that delegates permission checks like `Gate::allows('request.create')` to `PermissionService::userCan()`. This way existing policies still work, and new code can use `$user->can('voting.finalize')` naturally.

---

## Task 1.5 — `User` model: add `can($permission)` helper

In `app/Models/User.php`, override or add:
```php
public function hasPermission(string $slug): bool
{
    return app(PermissionService::class)->userCan($this, $slug);
}
```

Laravel's `$user->can('slug')` will already work via the Gate::before hook from Task 1.4, but `hasPermission()` is explicit and useful in policies.

---

## Task 1.6 — `merchants` table

Migration:
```bash
php artisan make:migration create_merchants_table
```

```php
$table->id();
$table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
$table->string('name');                        // الاسم التجاري
$table->string('commercial_register')->nullable()->unique();   // السجل التجاري
$table->string('tax_number')->nullable()->unique();            // الرقم الضريبي
$table->string('national_id')->nullable();                     // الرقم الوطني للمالك
$table->string('owner_name')->nullable();
$table->string('phone')->nullable();
$table->string('email')->nullable();
$table->text('address')->nullable();
$table->boolean('is_active')->default(true);
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamps();
$table->softDeletes();
$table->index('bank_id');
$table->index('is_active');
```

### Model `app/Models/Merchant.php`
- Relationships: `bank()`, `creator()`, `importRequests()`.
- Scope: `scopeActive()`, `scopeForUser(User $user)` (bank-scoped for bank roles, all for CBY).

### Policy `app/Policies/MerchantPolicy.php`
Use the new permission system:
- `viewAny`, `view`: any authenticated user (bank-scoped for bank users).
- `create`, `update`, `delete`: `$user->hasPermission('merchants.manage')` AND bank-scoping (bank users can only manage their bank's merchants).

### Controller `app/Http/Controllers/Api/MerchantController.php`

| Method | Endpoint |
|---|---|
| GET | `/api/merchants` (list, paginated, filter by `bank_id`, `is_active`, `search`) |
| POST | `/api/merchants` |
| GET | `/api/merchants/{id}` |
| PUT | `/api/merchants/{id}` |
| DELETE | `/api/merchants/{id}` (soft delete) |

FormRequests: `StoreMerchantRequest`, `UpdateMerchantRequest`. Resource: `MerchantResource`.

Bank scoping rule: when a `BANK_MANAGER` or `DATA_ENTRY` creates a merchant, `bank_id` is automatically set to their `bank_id` (don't trust user input). For `CBY_ADMIN`, `bank_id` must be provided in the body.

Add full Swagger `@OA` annotations on all 5 endpoints under tag `"التجار / Merchants"`.

---

## Task 1.7 — `document_types` table

Migration:
```bash
php artisan make:migration create_document_types_table
```

```php
$table->id();
$table->string('slug')->unique();              // e.g. 'commercial_invoice'
$table->string('name_ar');                     // 'فاتورة تجارية'
$table->string('name_en');                     // 'Commercial Invoice'
$table->boolean('is_required')->default(false);
$table->boolean('is_active')->default(true);
$table->unsignedSmallInteger('sort_order')->default(0);
$table->timestamps();
```

### Model `app/Models/DocumentType.php`
Standard model with fillable, casts, `scopeActive()`.

### Controller `app/Http/Controllers/Api/DocumentTypeController.php`

| Method | Endpoint |
|---|---|
| GET | `/api/document-types` |
| POST | `/api/document-types` |
| PUT | `/api/document-types/{id}` |
| DELETE | `/api/document-types/{id}` |

Authorization: only users with `docrules.manage` permission (only `CBY_ADMIN` per the matrix) for create/update/delete. GET is open to all authenticated users.

### Update `request_documents` table

Create a follow-up migration that adds a nullable `document_type_id` FK to `request_documents`:

```bash
php artisan make:migration add_document_type_to_request_documents
```
```php
$table->foreignId('document_type_id')->nullable()->after('type')->constrained('document_types')->nullOnDelete();
$table->index('document_type_id');
```

Existing rows stay `null` — backward compatible.

### Seeder `DocumentTypeSeeder`

Seed 8 common document types:
```
commercial_invoice    | فاتورة تجارية              | Commercial Invoice            | required
packing_list          | قائمة التعبئة              | Packing List                  | required
bill_of_lading        | سند الشحن                  | Bill of Lading                | required
certificate_of_origin | شهادة المنشأ                | Certificate of Origin         | required
import_license        | رخصة الاستيراد             | Import License                | required
insurance_policy      | بوليصة التأمين             | Insurance Policy              | optional
quality_certificate   | شهادة الجودة                | Quality Certificate           | optional
other                 | مستند آخر                   | Other                         | optional
```

Register the seeder in `DatabaseSeeder` after `PermissionSeeder`.

---

## Chunk 1 acceptance criteria

- [ ] `UserRole` enum has exactly **8 cases** matching the prototype.
- [ ] `COMMITTEE_DIRECTOR` is fully removed — `grep -rn "COMMITTEE_DIRECTOR" app/ database/ routes/ config/` returns nothing.
- [ ] `BANK_MANAGER` and `EXECUTIVE_DIRECTOR` cases exist with correct Arabic labels.
- [ ] `permissions` and `role_permissions` tables exist, seeded with 16 permissions.
- [ ] The matrix above is exactly reflected in `role_permissions`.
- [ ] `$user->can('voting.finalize')` works via Gate::before.
- [ ] `merchants` table exists, with CRUD endpoints documented in Swagger.
- [ ] `document_types` table exists with seeded values.
- [ ] `request_documents.document_type_id` column added (nullable).
- [ ] No existing endpoint is broken.

Run:
```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=DocumentTypeSeeder
php artisan l5-swagger:generate
```

---

---

# 📦 CHUNK 2 — Workflow Rule Changes (Editing Lock, BANK_MANAGER Capabilities, Director Roles)

## Goal

1. Editability follows ownership: only editable when `current_owner_role === DATA_ENTRY`.
2. `BANK_MANAGER` can perform actions of `DATA_ENTRY`, `BANK_REVIEWER`, and `SWIFT_OFFICER` within their own bank.
3. Voting finalization moves from `COMMITTEE_DIRECTOR` to `EXECUTIVE_DIRECTOR`.
4. Customs issuance moves from `CBY_ADMIN` to `EXECUTIVE_DIRECTOR`.
5. Executive Director has override power (can confirm OR overturn the voting result).

---

## Task 2.1 — Update `ImportRequest::isEditable()`

In `app/Models/ImportRequest.php`, replace the existing `isEditable()` implementation with:

```php
public function isEditable(): bool
{
    return $this->current_owner_role === UserRole::DATA_ENTRY
        && in_array($this->status, [
            RequestStatus::DRAFT,
            RequestStatus::RETURNED_TO_DATA_ENTRY,
        ], true);
}
```

This guarantees: the moment Bank Reviewer (or `BANK_MANAGER` acting as one) approves, the request is locked — even before Support Committee starts reviewing.

---

## Task 2.2 — Update `ImportRequestPolicy`

In `app/Policies/ImportRequestPolicy.php`:

- `create`: allow if `$user->hasPermission('request.create')`. (Now `DATA_ENTRY` AND `BANK_MANAGER`.)
- `update`: allow if `$user->hasPermission('request.create')` AND `$request->isEditable()` AND same bank.
- `delete`: allow if `$user->hasPermission('request.create')` AND `$request->status === DRAFT` AND same bank.

All checks switch from `$user->role === ...` hardcoded comparisons to permission-based checks. **Keep bank-scoping logic intact.**

---

## Task 2.3 — Update `TransitionMap`

Open `app/Services/Workflow/TransitionMap.php`. The transition map must allow `BANK_MANAGER` everywhere a `DATA_ENTRY`, `BANK_REVIEWER`, or `SWIFT_OFFICER` is required.

**Change the `required_role` field from a single enum to an array of allowed roles.**

New shape (use named keys for readability — refactor the structure):

```php
'submit' => [
    'from'        => [RequestStatus::DRAFT, RequestStatus::RETURNED_TO_DATA_ENTRY],
    'to'          => RequestStatus::SUBMITTED,
    'roles'       => [UserRole::DATA_ENTRY, UserRole::BANK_MANAGER],
    'next_owner'  => UserRole::BANK_REVIEWER,
],
'bank_approve' => [
    'from'        => [RequestStatus::SUBMITTED],
    'to'          => RequestStatus::BANK_APPROVED,
    'roles'       => [UserRole::BANK_REVIEWER, UserRole::BANK_MANAGER],
    'next_owner'  => UserRole::SUPPORT_COMMITTEE,
],
'bank_reject' => [
    'from'        => [RequestStatus::SUBMITTED],
    'to'          => RequestStatus::BANK_REJECTED,
    'roles'       => [UserRole::BANK_REVIEWER, UserRole::BANK_MANAGER],
    'next_owner'  => null,
],
'return_to_entry' => [
    'from'        => [RequestStatus::SUBMITTED, RequestStatus::SUPPORT_REJECTED, RequestStatus::EXECUTIVE_REJECTED],
    'to'          => RequestStatus::RETURNED_TO_DATA_ENTRY,
    'roles'       => [UserRole::BANK_REVIEWER, UserRole::BANK_MANAGER],
    'next_owner'  => UserRole::DATA_ENTRY,
],
'support_approve' => [
    'from'        => [RequestStatus::BANK_APPROVED],
    'to'          => RequestStatus::SUPPORT_APPROVED,
    'roles'       => [UserRole::SUPPORT_COMMITTEE],
    'next_owner'  => UserRole::SWIFT_OFFICER,
],
'support_reject' => [
    'from'        => [RequestStatus::BANK_APPROVED],
    'to'          => RequestStatus::SUPPORT_REJECTED,
    'roles'       => [UserRole::SUPPORT_COMMITTEE],
    'next_owner'  => UserRole::BANK_REVIEWER,
],
'swift_upload' => [
    'from'        => [RequestStatus::SUPPORT_APPROVED],
    'to'          => RequestStatus::SWIFT_UPLOADED,
    'roles'       => [UserRole::SWIFT_OFFICER, UserRole::BANK_MANAGER],
    'next_owner'  => UserRole::EXECUTIVE_MEMBER,
],
'finalize_approved' => [
    'from'        => [RequestStatus::EXECUTIVE_VOTING],
    'to'          => RequestStatus::EXECUTIVE_APPROVED,
    'roles'       => [UserRole::EXECUTIVE_DIRECTOR],   // CHANGED from COMMITTEE_DIRECTOR
    'next_owner'  => UserRole::EXECUTIVE_DIRECTOR,
],
'finalize_rejected' => [
    'from'        => [RequestStatus::EXECUTIVE_VOTING],
    'to'          => RequestStatus::EXECUTIVE_REJECTED,
    'roles'       => [UserRole::EXECUTIVE_DIRECTOR],   // CHANGED
    'next_owner'  => UserRole::BANK_REVIEWER,
],
'issue_customs' => [
    'from'        => [RequestStatus::EXECUTIVE_APPROVED],
    'to'          => RequestStatus::CUSTOMS_ISSUED,
    'roles'       => [UserRole::EXECUTIVE_DIRECTOR],   // CHANGED from CBY_ADMIN
    'next_owner'  => UserRole::EXECUTIVE_DIRECTOR,
],
'complete' => [
    'from'        => [RequestStatus::CUSTOMS_ISSUED],
    'to'          => RequestStatus::COMPLETED,
    'roles'       => [UserRole::EXECUTIVE_DIRECTOR],
    'next_owner'  => null,
],
```

**Note:** the legacy single-key array shape used by `WorkflowService` must be refactored to read from this new named-keys shape. Update the service accordingly.

---

## Task 2.4 — Update `WorkflowService::transition()`

Modify the role check inside `transition()`:

```php
// OLD:
// if ($actor->role !== $transition['required_role']) { throw ...; }

// NEW:
$allowedRoles = $transition['roles'];
if (!in_array($actor->role, $allowedRoles, true)) {
    throw new UnauthorizedTransitionException(
        'هذا الإجراء غير مسموح لدورك الحالي. / This action is not allowed for your current role.'
    );
}
```

**Self-review rule** (existing): keep the check that `$actor->id !== $request->created_by` for `bank_approve`. But it should also apply when a `BANK_MANAGER` is the actor (a bank manager who created the request can't approve their own creation either).

---

## Task 2.5 — Update `VotingService::finalize()`

In `app/Services/Voting/VotingService.php`, the `finalize()` method (called when there's a tie or when director closes voting):

- Change required role from `COMMITTEE_DIRECTOR` to `EXECUTIVE_DIRECTOR`.
- Add a new method `overrideAndFinalize()`:

```php
public function overrideAndFinalize(
    ImportRequest $request,
    User $director,
    VoteType $finalDecision,
    string $justification
): ImportRequest
```

**Logic:**
- Verify `$director->role === UserRole::EXECUTIVE_DIRECTOR`.
- Verify `$request->status === EXECUTIVE_VOTING`.
- Verify `$justification` is non-empty (required when overriding the voting majority).
- Insert a `request_votes` row with `is_director_override = true` and `vote = $finalDecision`.
- Call `WorkflowService::transition()` with `finalize_approved` or `finalize_rejected` based on `$finalDecision`.
- Audit log includes `was_override: true` and the current tally at override time.

Difference from the old `finalize()`:
- Old `finalize()` only worked on a tie (3-3 deadlock); the director picked one side.
- New `overrideAndFinalize()` works **regardless of tally** — even if 5 members voted approve, the Executive Director can override with reject + justification.

**Important:** Since `COMMITTEE_DIRECTOR` is fully removed (Chunk 1), all logic in `VotingService` that previously branched on `COMMITTEE_DIRECTOR` must now use `EXECUTIVE_DIRECTOR`. There is no fallback path.

---

## Task 2.6 — Update voting endpoints

In `app/Http/Controllers/Api/VotingController.php`:

- Change `POST /api/voting/{id}/director-decide` to require `EXECUTIVE_DIRECTOR` instead of `COMMITTEE_DIRECTOR`.
- Add a new endpoint:

```
POST /api/voting/{id}/override
```

Body:
```json
{
  "decision": "APPROVE" | "REJECT",
  "justification": "required text explaining the override"
}
```

Authorization: only `EXECUTIVE_DIRECTOR` (`$user->hasPermission('voting.finalize')`).

Action: calls `VotingService::overrideAndFinalize()`.

Add Swagger annotations.

---

## Task 2.7 — Update Customs endpoints authorization

In `app/Http/Controllers/Api/CustomsController.php`:

- `POST /api/customs/{request_id}/generate`: authorization changes from `$user->role === CBY_ADMIN` to `$user->hasPermission('customs.issue')` (which is only `EXECUTIVE_DIRECTOR`).
- Update `CustomsService::generate()` to verify `$issuer->role === EXECUTIVE_DIRECTOR` (changed from `CBY_ADMIN`).
- The customs declaration's `issued_by` still points to the user, no schema change needed.

---

## Task 2.8 — Update `ImportRequest` creation flow for merchant linkage

This is a small but critical addition. Add a `merchant_id` column to `import_requests`:

```bash
php artisan make:migration add_merchant_id_to_import_requests
```

```php
$table->foreignId('merchant_id')->nullable()->after('bank_id')->constrained('merchants')->nullOnDelete();
$table->index('merchant_id');
```

Update `ImportRequest` model:
- Add relationship `merchant()`.
- Add `merchant_id` to fillable.

Update `StoreImportRequest` FormRequest:
- Add `merchant_id` field, required.
- Validation: merchant must exist AND belong to the same bank as the creating user.

Update `UpdateImportRequest` similarly.

Update `ImportRequestResource` to include `merchant` (id, name, commercial_register).

**Backward compatibility:** existing seeded requests have `merchant_id = null`. Codex must update the seeder (Chunk 4) to backfill these.

---

## Chunk 2 acceptance criteria

- [ ] `isEditable()` returns false the moment status leaves `DRAFT` or `RETURNED_TO_DATA_ENTRY`.
- [ ] `BANK_MANAGER` can create requests, approve them as reviewer (but NOT their own), and upload SWIFT.
- [ ] A `BANK_MANAGER` who created a request gets a 403 if they try to bank-approve it (self-review rule).
- [ ] `EXECUTIVE_DIRECTOR` can finalize voting and issue customs.
- [ ] `CBY_ADMIN` can NO LONGER issue customs (gets 403).
- [ ] New endpoint `POST /api/voting/{id}/override` works for `EXECUTIVE_DIRECTOR` with required justification.
- [ ] `import_requests.merchant_id` column exists and is required on create.
- [ ] No existing tests/seeds break (other than the customs ownership and merchant FK).

Run:
```bash
php artisan migrate
php artisan l5-swagger:generate
```

---

---

# 📦 CHUNK 3 — Support Committee Claim Mechanism

## Goal

Implement the "حجز للمراجعة" (claim for review) mechanism so only one Support Committee member works on a request at a time.

---

## Task 3.1 — Migration: add claim columns to `import_requests`

```bash
php artisan make:migration add_support_claim_columns_to_import_requests
```

```php
$table->foreignId('claimed_by')->nullable()->after('current_owner_role')->constrained('users')->nullOnDelete();
$table->timestamp('claimed_at')->nullable()->after('claimed_by');
$table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
$table->index('claimed_by');
$table->index('claim_expires_at');
```

---

## Task 3.2 — Add new status `SUPPORT_UNDER_REVIEW`

In `app/Enums/RequestStatus.php`, add:
```php
case SUPPORT_UNDER_REVIEW = 'SUPPORT_UNDER_REVIEW';
```

Label: `'قيد مراجعة لجنة الدعم / Under Support Review'`.

---

## Task 3.3 — Config file `config/workflow.php`

Create:
```php
return [
    'support_claim_ttl_hours' => env('SUPPORT_CLAIM_TTL_HOURS', 24),
];
```

---

## Task 3.4 — `ImportRequest` model additions

```php
// casts
'claimed_at'       => 'datetime',
'claim_expires_at' => 'datetime',

// relationship
public function claimedBy() {
    return $this->belongsTo(User::class, 'claimed_by');
}

// helpers
public function isClaimed(): bool {
    return $this->claimed_by !== null
        && $this->claim_expires_at !== null
        && $this->claim_expires_at->isFuture();
}

public function isClaimedBy(User $user): bool {
    return $this->isClaimed() && $this->claimed_by === $user->id;
}

public function isClaimExpired(): bool {
    return $this->claimed_by !== null
        && ($this->claim_expires_at === null || $this->claim_expires_at->isPast());
}
```

---

## Task 3.5 — Update `TransitionMap`: replace support_approve/support_reject

Replace the entries for `support_approve` and `support_reject` so they require the request to be in `SUPPORT_UNDER_REVIEW`, and add two new transitions:

```php
'support_claim' => [
    'from'        => [RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_UNDER_REVIEW],
    'to'          => RequestStatus::SUPPORT_UNDER_REVIEW,
    'roles'       => [UserRole::SUPPORT_COMMITTEE],
    'next_owner'  => UserRole::SUPPORT_COMMITTEE,
],
'support_release' => [
    'from'        => [RequestStatus::SUPPORT_UNDER_REVIEW],
    'to'          => RequestStatus::BANK_APPROVED,
    'roles'       => [UserRole::SUPPORT_COMMITTEE],
    'next_owner'  => UserRole::SUPPORT_COMMITTEE,
],
'support_approve' => [
    'from'        => [RequestStatus::SUPPORT_UNDER_REVIEW],   // CHANGED
    'to'          => RequestStatus::SUPPORT_APPROVED,
    'roles'       => [UserRole::SUPPORT_COMMITTEE],
    'next_owner'  => UserRole::SWIFT_OFFICER,
],
'support_reject' => [
    'from'        => [RequestStatus::SUPPORT_UNDER_REVIEW],   // CHANGED
    'to'          => RequestStatus::SUPPORT_REJECTED,
    'roles'       => [UserRole::SUPPORT_COMMITTEE],
    'next_owner'  => UserRole::BANK_REVIEWER,
],
```

**Key point:** Support members can no longer go directly from `BANK_APPROVED` to approve/reject. They MUST claim first.

---

## Task 3.6 — Update `WorkflowService` for claim semantics

Add a private helper:

```php
private function applyClaimSideEffects(ImportRequest $request, string $action, User $actor): void
{
    $ttl = config('workflow.support_claim_ttl_hours', 24);

    match ($action) {
        'support_claim' => $request->forceFill([
            'claimed_by'       => $actor->id,
            'claimed_at'       => now(),
            'claim_expires_at' => now()->addHours($ttl),
        ]),
        'support_release', 'support_approve', 'support_reject' => $request->forceFill([
            'claimed_by'       => null,
            'claimed_at'       => null,
            'claim_expires_at' => null,
        ]),
        default => null,
    };
}
```

Call it inside the DB transaction in `transition()`, after status/owner updates and before `$request->save()`.

Add authorization checks inside `transition()`:

```php
if (in_array($action, ['support_approve', 'support_reject'], true)) {
    if (!$request->isClaimedBy($actor)) {
        throw new UnauthorizedTransitionException(
            'لا يمكنك اتخاذ قرار على طلب لم تقم بحجزه. / You cannot decide on a request you have not claimed.'
        );
    }
}

if ($action === 'support_claim') {
    // If actively claimed by someone else AND not expired → only allow override via separate logic (any support member can override)
    if ($request->isClaimed() && $request->claimed_by !== $actor->id) {
        // Allowed: another support member is overriding the claim.
        // No exception — just proceed; the side-effect will reassign claimed_by.
        // Audit log will note that this is an override.
        $metadata['override_previous_claim_by'] = $request->claimed_by;
    }
}

if ($action === 'support_release') {
    // Anyone in SUPPORT_COMMITTEE can release any claim
    if (!$request->isClaimed()) {
        throw new InvalidTransitionException('الطلب غير محجوز. / Request is not currently claimed.');
    }
}
```

Audit log entries for `support_claim` should include `claimed_until` and `override_previous_claim_by` (if applicable) in metadata.

---

## Task 3.7 — Workflow endpoints for claim/release

Add to `WorkflowController`:

| Method | Endpoint | Action |
|---|---|---|
| POST | `/api/workflow/{id}/support-claim` | support_claim |
| POST | `/api/workflow/{id}/support-release` | support_release |

Both endpoints:
- Require `request.claim` permission (which is SUPPORT_COMMITTEE only).
- Return updated `ImportRequestResource`.
- Full Swagger annotations.

---

## Task 3.8 — Update the requests list response to expose claim info

In `ImportRequestResource`:
- Add `claimed_by` (id, name) when not null.
- Add `claimed_until` ISO timestamp.
- Add `is_claimed` boolean.
- Add `is_claimed_by_me` boolean (computed against current user).
- Add `can_be_claimed` boolean: true when status is `BANK_APPROVED` (anyone can claim) OR status is `SUPPORT_UNDER_REVIEW` AND `claim_expires_at` is past.

These help the frontend render the claim button correctly without re-computing.

---

## Task 3.9 — Filter the support queue endpoint

In `ImportRequestController::index()`, add a query param `?claim_filter=available|mine|all` (used only by support committee users):
- `available` → status is `BANK_APPROVED` OR (`SUPPORT_UNDER_REVIEW` AND claim expired)
- `mine` → claimed_by = current user AND claim active
- `all` (default) → no filter

This lets the frontend show "Available for me" vs "I claimed" tabs.

---

## Chunk 3 acceptance criteria

- [ ] `import_requests.claimed_by`, `claimed_at`, `claim_expires_at` columns exist.
- [ ] Status `SUPPORT_UNDER_REVIEW` exists in enum and labels.
- [ ] Support member can `support_claim` from `BANK_APPROVED`, gets the request locked to them.
- [ ] Another support member calling `support_approve` gets 403 ("لا يمكنك اتخاذ قرار على طلب لم تقم بحجزه").
- [ ] Another support member can call `support_claim` again to override the claim.
- [ ] After 24 hours of inactivity, any support member can re-claim (test by manually setting `claim_expires_at` to past).
- [ ] On approve/reject, claim is automatically cleared.
- [ ] Audit logs show override events with `override_previous_claim_by`.

Run:
```bash
php artisan migrate
php artisan l5-swagger:generate
```

---

---

# 📦 CHUNK 4 — Seeder Updates, Test Page Updates, Cleanup

## Goal

Update the seeder to reflect all changes from Chunks 1–3, and update the test page to expose the new endpoints and roles.

---

## Task 4.1 — Update `UserSeeder` to mirror the prototype's login page

The seeded users must reflect the **exact names** shown in the prototype's login page at `yemen-flow-hub.lovable.app/login`, so the demo experience matches one-to-one.

### Reset and rebuild the user list

Open `database/seeders/UserSeeder.php` and replace the entire user list with the following. Delete any user that no longer fits the 8-role structure (especially any `COMMITTEE_DIRECTOR` user from before).

All passwords are `password` (bcrypted).

### CBY users (no `bank_id`) — 9 users

| Name (Arabic) | Email | Role |
|---|---|---|
| ياسر الحضرمي | admin@cby.gov.ye | CBY_ADMIN |
| محمد الشامي | support1@cby.gov.ye | SUPPORT_COMMITTEE |
| نسيم العمري | support2@cby.gov.ye | SUPPORT_COMMITTEE |
| د. هدى الإرياني | director@cby.gov.ye | EXECUTIVE_DIRECTOR |
| م. سامي الذماري | exec1@cby.gov.ye | EXECUTIVE_MEMBER |
| د. ندى الكبسي | exec2@cby.gov.ye | EXECUTIVE_MEMBER |
| أ. فهد الشرعبي | exec3@cby.gov.ye | EXECUTIVE_MEMBER |
| د. أمينة العزب | exec4@cby.gov.ye | EXECUTIVE_MEMBER |
| م. خالد الأنسي | exec5@cby.gov.ye | EXECUTIVE_MEMBER |
| محمود الذيباني | exec6@cby.gov.ye | EXECUTIVE_MEMBER |

**Note:** The prototype showed "د. هدى الإرياني" twice (once as executive member, once as director) — this was a prototype display issue. In our seeder she is **the executive director only**. The 6 executive members use the names from the prototype's executive committee section.

### Bank users — 4 users per active bank

The prototype highlighted **"البنك اليمني للإنشاء والتعمير"** with these specific users:

| Name | Role |
|---|---|
| أحمد المقطري | BANK_MANAGER |
| علي القاضي | DATA_ENTRY |
| نوال الحاج | BANK_REVIEWER |
| سامي العتمي | SWIFT_OFFICER |

Replace the seeder's first active bank with **"البنك اليمني للإنشاء والتعمير"** (code `YBRD`) and assign these exact users to it (emails `manager@ybrd.com.ye`, `entry@ybrd.com.ye`, `reviewer@ybrd.com.ye`, `swift@ybrd.com.ye`).

For the other 3 active banks, generate 4 bank users each using realistic Arabic names from the existing pool (or faker if needed). Email pattern: `{role}@{code}.com.ye`.

| Bank | Code | BANK_MANAGER | DATA_ENTRY | BANK_REVIEWER | SWIFT_OFFICER |
|---|---|---|---|---|---|
| البنك اليمني للإنشاء والتعمير | YBRD | أحمد المقطري | علي القاضي | نوال الحاج | سامي العتمي |
| بنك التضامن الإسلامي الدولي | TIIB | (generate) | (generate) | (generate) | (generate) |
| البنك التجاري اليمني | YCB | (generate) | (generate) | (generate) | (generate) |
| بنك سبأ الإسلامي | SIB | (generate) | (generate) | (generate) | (generate) |

(Keep one inactive bank for edge cases — `NBY` "البنك الأهلي اليمني" with no users.)

**Total users:** 10 CBY + (4 × 4) bank users = **26 users**.

### Print summary

At the end of the seeder, print a table:
```
✓ CBY users:
  - 1 CBY_ADMIN
  - 2 SUPPORT_COMMITTEE
  - 6 EXECUTIVE_MEMBER
  - 1 EXECUTIVE_DIRECTOR
✓ Bank users (4 banks × 4 roles):
  - 4 BANK_MANAGER
  - 4 DATA_ENTRY
  - 4 BANK_REVIEWER
  - 4 SWIFT_OFFICER
✓ Total: 26 users
```

### Important — also delete legacy committee director user

If a user record exists with role `COMMITTEE_DIRECTOR` from the original Module 2 seeder, the new seeder must NOT recreate it. Verify after `migrate:fresh --seed`:
```sql
SELECT COUNT(*) FROM users WHERE role = 'COMMITTEE_DIRECTOR';
-- Must return 0
```

---

## Task 4.2 — Create `MerchantSeeder`

Seed 3 merchants per active bank (12 total):

For each bank, generate names like:
- `"شركة الهدى للتجارة"` / `"Al-Hadi Trading LLC"`
- `"مؤسسة النور للاستيراد"` / `"Al-Noor Import Est."`
- `"شركة اليمن الذهبية"` / `"Golden Yemen Co."`

Each merchant has:
- realistic `commercial_register` (e.g. `"CR-2024-{rand6}"`)
- realistic `tax_number`
- `owner_name` (Arabic name)
- `phone` (Yemeni format `+967 7X XXX XXXX`)
- random `is_active = true` (10), 2 inactive
- `created_by` = the bank's `BANK_MANAGER`

Register in `DatabaseSeeder` after `BankSeeder` and `UserSeeder`.

---

## Task 4.3 — Update `RequestScenarioBuilder`

In `database/seeders/Support/RequestScenarioBuilder.php`:

1. Every request now needs a `merchant_id`. Pick a random merchant from the bank's pool.
2. For scenarios where status is `BANK_APPROVED` AND we want to demonstrate the claim mechanism, add a new scenario:

   | New scenario key | Status | Count | Notes |
   |---|---|---|---|
   | `support_under_review_claimed` | SUPPORT_UNDER_REVIEW | 2 | Claimed by a specific support member, `claim_expires_at` = now + 23h |
   | `support_claim_expired` | SUPPORT_UNDER_REVIEW | 1 | Claim is expired (`claim_expires_at` in past), available for re-claim |

3. For scenarios that previously used `COMMITTEE_DIRECTOR` for finalization, change the actor to `EXECUTIVE_DIRECTOR`. Specifically:
   - Scenario 10 (`executive_approved_no_customs_yet`): the `finalize_approved` history entry's `actor_id` = the executive director user.
   - Scenario 11 (`executive_rejected_returned`): same — actor is executive director.
   - Scenarios 12, 13, 14 (customs flows): `customs_issued` and `complete` actions now performed by executive director, not CBY admin.

4. Update the customs declaration's `issued_by` to point to the executive director user (not CBY admin).

5. Print final scenario summary including the 2 new claim scenarios.

---

## Task 4.4 — Update `AuditLogSeeder` and supporting helpers

The audit logs that reference workflow transitions need their `user_role` updated for the customs-related transitions to `EXECUTIVE_DIRECTOR`.

No new columns needed — just ensure the seeded data is internally consistent.

---

## Task 4.5 — Update the test page (`resources/views/test_api.blade.php`)

### 4.5.1 — Add new endpoints to the relevant tabs

**Tab "التجار / Merchants" (NEW TAB):**

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/merchants | قائمة التجار | bank_id, is_active, search |
| POST | /api/merchants | إضافة تاجر | name, commercial_register, tax_number, owner_name, phone, email, address, bank_id (CBY only) |
| GET | /api/merchants/{id} | تفاصيل تاجر | id |
| PUT | /api/merchants/{id} | تعديل تاجر | id + fields |
| DELETE | /api/merchants/{id} | حذف تاجر | id |

**Tab "أنواع المستندات / Document Types" (NEW TAB):**

| Method | Path | الوصف |
|---|---|---|
| GET | /api/document-types | قائمة أنواع المستندات |
| POST | /api/document-types | إضافة نوع مستند |
| PUT | /api/document-types/{id} | تعديل |
| DELETE | /api/document-types/{id} | حذف |

**Tab "سير العمل / Workflow" — add these new actions:**

| Method | Path | الوصف |
|---|---|---|
| POST | /api/workflow/{id}/support-claim | حجز الطلب للمراجعة |
| POST | /api/workflow/{id}/support-release | إلغاء حجز الطلب |

**Tab "التصويت / Voting" — add:**

| Method | Path | الوصف |
|---|---|---|
| POST | /api/voting/{id}/override | قرار مدير اللجنة التنفيذية (override) |

### 4.5.2 — Add new request field to the request-create card

In the "إنشاء طلب جديد" card (`POST /api/requests`), add a new field at the top:
- `merchant_id` — required.

Best UX: render this as a dropdown populated dynamically. When the user clicks the card, fetch `/api/merchants` and populate the dropdown with merchants belonging to the current user's bank.

### 4.5.3 — Update the user list in section 1 (Quick Login)

Since the seeder now creates 27 users including 1 new role (`EXECUTIVE_DIRECTOR`) and 4 `BANK_MANAGER`s, the quick-login list will auto-populate them (the view loops over `$users`). Just verify:

- New users appear and are filterable.
- Role labels render correctly in Arabic via `role_label`.
- Grouping by role is sensible.

### 4.5.4 — Update endpoint group filter

Verify the tab filter logic still works after adding two new tabs (Merchants, Document Types). The total tab count is now 14:

```
المصادقة، البنوك، المستخدمون، طلبات التمويل، سير العمل، التصويت، المستندات،
البيان الجمركي، سجلات التدقيق، الإشعارات، لوحة المعلومات، التقارير،
التجار، أنواع المستندات
```

---

## Task 4.6 — Cleanup and verification

1. Run `php artisan migrate:fresh --seed` end-to-end. Verify:
   - **26 users** with 8 distinct roles (no COMMITTEE_DIRECTOR).
   - Login page shows the 4 prototype users for "البنك اليمني للإنشاء والتعمير": أحمد المقطري, علي القاضي, نوال الحاج, سامي العتمي.
   - 12 merchants
   - 8 document types
   - ~30 requests (27 original scenarios + 3 new claim scenarios)
   - All FKs consistent.
   - `SELECT COUNT(*) FROM users WHERE role = 'COMMITTEE_DIRECTOR'` returns 0.

2. Run `php artisan l5-swagger:generate`. Verify no annotation errors.

3. Open `http://localhost:8000/test-api`. Verify:
   - Login as `EXECUTIVE_DIRECTOR` (د. هدى الإرياني) works. Verify they have access to voting finalize + customs issue.
   - Login as `BANK_MANAGER` (أحمد المقطري). Verify they can create a request, see their bank's merchants, and approve requests not created by themselves.
   - Login as a support committee member. Claim a `BANK_APPROVED` request. Try to claim it as another support member — verify the override works and audit logs note it.
   - Verify `CBY_ADMIN` is now blocked from issuing customs (403).

4. Delete any temporary debug code and dummy comments.

---

## Chunk 4 acceptance criteria

- [ ] Seeder produces **26 users** with **8 distinct roles** (no `COMMITTEE_DIRECTOR`).
- [ ] The 4 prototype users for "البنك اليمني للإنشاء والتعمير" exist with the exact names from the prototype: أحمد المقطري (BANK_MANAGER), علي القاضي (DATA_ENTRY), نوال الحاج (BANK_REVIEWER), سامي العتمي (SWIFT_OFFICER).
- [ ] د. هدى الإرياني is seeded as the only `EXECUTIVE_DIRECTOR`.
- [ ] 6 executive committee members are seeded with the prototype names.
- [ ] 12 merchants seeded, distributed across 4 active banks.
- [ ] All 8 document types seeded.
- [ ] All seeded requests have a `merchant_id`.
- [ ] 3 new claim scenarios visible.
- [ ] Test page has new tabs (Merchants, Document Types) and new endpoints (claim/release, override).
- [ ] `merchant_id` dropdown on create-request card works.
- [ ] Full clean-slate run (`migrate:fresh --seed && l5-swagger:generate`) completes with no errors.

Run:
```bash
php artisan migrate:fresh --seed
php artisan l5-swagger:generate
php artisan serve
```

Then visit `http://localhost:8000/test-api`.

---

# 🏁 Final acceptance checklist (whole update)

After all 4 chunks are applied:

- [ ] **Exactly 8 roles** exist with correct Arabic labels matching the prototype.
- [ ] **`COMMITTEE_DIRECTOR` is fully removed** — no occurrences in code, no users in DB.
- [ ] **16 permissions** mapped per the matrix.
- [ ] **`$user->can('permission.slug')`** works end-to-end.
- [ ] **Editing lock**: request becomes uneditable the moment Bank Reviewer (or BANK_MANAGER) approves.
- [ ] **BANK_MANAGER** can create, review, approve, and upload SWIFT within their own bank — but never self-review.
- [ ] **Merchants** table + CRUD + linked to requests.
- [ ] **Document types** table + CRUD.
- [ ] **Support claim** mechanism: only one member at a time, 24h TTL, override allowed, audit-logged.
- [ ] **Voting finalization + customs issuance** are now `EXECUTIVE_DIRECTOR` only.
- [ ] **Executive Director override** endpoint exists, requires justification, can flip the voting result.
- [ ] **`CBY_ADMIN`** no longer issues customs.
- [ ] **Test page** reflects all new endpoints and the new roles in quick-login.
- [ ] **Login experience matches the prototype** — same names, same banks, same roles.
- [ ] **Swagger** has no annotation errors, groups endpoints by Arabic tag names.
