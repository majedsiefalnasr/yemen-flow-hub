# WP-11 — Settings Truth Wave Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Collapse the three disjoint settings systems into one DB-driven, consumer-backed truth; remove every placebo setting; make the remaining settings actually change runtime behavior.

**Architecture:** `AdminSettingsService` (scalar row-per-key) becomes the single source for operational/security settings. `AuthSecuritySettings` already reads DB-first-then-config — we register the live keys there and remove config fallbacks once DB rows exist. The disconnected consumers (`EngineClaimService`, 5 `max:10240` form-request literals, `DuplicateInvoiceChecker`) read the DB setting. Dead settings are deleted from `DEFAULTS`/`VALIDATION_RULES`/seeder/UI. SMTP panel and maintenance page are deleted (env-only mail; no maintenance backend). Logo migrates from a 3MB base64 string to a storage file. Cache reads are added where invalidation already fires.

**Tech Stack:** PHP 8.2+, Laravel 11, MySQL, Sanctum. Nuxt 4 + Vue + TS frontend.

## Investigation findings (the starting state)

There are **three disjoint settings systems**:

| System | Storage | Reads at runtime? |
|---|---|---|
| A. `SystemSettingsService` (JSON blob per section: `settings.general`, `settings.branding`, `settings.email`) | `system_settings` row per section | general+branding only (public page); `settings.email.templates` blob is **dead** |
| B. `AdminSettingsService` (one row per scalar key, `DEFAULTS` const + `VALIDATION_RULES`) | `system_settings` row per key | mostly dead; only `login_lockout_duration` + `mfa_required` reach runtime via `AuthSecuritySettings` |
| C. `config/auth_security.php` + `config/mfa.php` + `config/workflow.php` | `.env` via `config()` | **this is what runs** for lockout/MFA/claim-TTL |

`AuthSecuritySettings::resolve($settingKey, $configKey)` (backend/app/Services/Auth/AuthSecuritySettings.php:44) is the existing DB-first-then-config primitive. WP-11 reuses it.

### The 15 placebo keys to remove (all in `AdminSettingsService::DEFAULTS`)

`voting_session_timeout`, `support_committee_size`, `executive_committee_size`, `minimum_quorum`, `review_timeout_hours`, `secret_voting`, `director_tiebreak`, `notifications_phase_1_enabled`, `search_phase_1_enabled`, `customs_print_preview_enabled`, `password_expiry_90_days`, `lockout_after_5_attempts`, `encrypt_uploads_aes256`, `log_all_audit`, `allow_external_access`.

### The live keys to wire (DB row becomes the truth)

| Key | Default | Validation | Runtime consumer (currently reads…) |
|---|---|---|---|
| `support_claim_ttl` | 15 | min 5, max 60 minutes | `EngineClaimService::ttlMinutes()` reads `config('workflow.support_claim_ttl_minutes')` |
| `pdf_upload_size_limit` | 10 | min 1, max 50 MB | 5 hardcoded `max:10240` (KB) literals in form requests |
| `login_lockout_attempts` | 5 | min 1, max 20 | `AuthSecuritySettings::lockoutAttempts()` (DB key NOT in DEFAULTS today → always falls to config) |
| `login_lockout_duration` | 15 | min 5, max 60 min | `AuthSecuritySettings::lockoutDurationMinutes()` (already wired DB-first) |
| `mfa_required` | true | boolean | `AuthSecuritySettings::mfaRequired()` (OR'd with `config('mfa.enabled')` in AuthController) |
| `duplicate_invoice_policy` | `warn` | enum warn\|block | `DuplicateInvoiceChecker` returns detection payload only — never consulted |
| `trusted_device_ttl_hours` | 24 | min 1, max 720 | `AuthSecuritySettings::trustedDeviceTtlHours()` (config-only; no admin row creatable) |
| `step_up_window_minutes` | 10 | min 1, max 120 | `AuthSecuritySettings::stepUpWindowMinutes()` (config-only) |

## Global Constraints

- **No-placebo principle:** no setting exposed in admin UI or API without an active runtime consumer. Every remaining key must have a test proving that changing it changes behavior.
- **Single MFA switch:** `mfa_required` (DB) is the only MFA gate. `config('mfa.enabled')` (env) is bootstrap-only; remove the OR in `AuthController::mfaRequiredFor()` once DB row is seeded.
- **Security posture unchanged mid-wave:** keep the existing live defaults (lockout 5 attempts / 15 min, claim 15 min, upload 10MB, mfa default per existing `mfa_required=true` in DEFAULTS but `config('auth_security.mfa_required')=false`). Do NOT change effective runtime values — only change WHERE they come from. If a default differs between config and DB, the DB wins going forward and the discrepancy is recorded, not silently fixed.
- **Signed commits only.** Never `--no-gpg-sign`/`--no-verify`. Conventional commits, required scope `settings` (or `auth`/`backend`/`frontend` where more apt). Co-Author trailer mandatory.
- **Verification ladder:** focused tests per task. Do not run full `php artisan test` (known-red baseline ~75 pre-existing failures). Report baseline, don't chase unrelated reds.
- **TDD:** failing test first for every behavior change.
- **No mock/fake data** in implementation (project rule). Tests use factories.
- **`AuthSecuritySettings` resolve primitive is reused, not rewritten** — unless a task says otherwise, route new DB-backed settings through it.

---

## File Structure

**Backend — modify:**
- `backend/app/Services/Settings/AdminSettingsService.php` — DEFAULTS + VALIDATION_RULES trim; add `login_lockout_attempts`/`trusted_device_ttl_hours`/`step_up_window_minutes`; cache read-through; remove SMTP methods + `getEmailTemplates()`.
- `backend/app/Services/Auth/AuthSecuritySettings.php` — add `duplicateInvoicePolicy()` not applicable here; instead consume the new TTL/size keys via resolve.
- `backend/app/Services/Workflow/EngineClaimService.php:17-19` — `ttlMinutes()` reads DB setting.
- `backend/app/Services/Workflow/DuplicateInvoiceChecker.php` — consult `duplicate_invoice_policy`.
- `backend/app/Http/Requests/UploadSwiftRequest.php`, `UploadDocumentRequest.php`, `FxConfirmationUploadRequest.php` — dynamic upload size.
- `backend/app/Http/Controllers/Api/EngineRequestDocumentController.php:37,171` — dynamic upload size.
- `backend/app/Http/Controllers/Api/AdminSettingsController.php` — remove SMTP endpoints + testEmail + getEmailTemplates surface.
- `backend/routes/api.php:280-282` — remove SMTP routes.
- `backend/app/Services/Settings/SystemSettingsService.php` — remove dead `settings.email.templates` blob write; logo-to-file migration.
- `backend/app/Http/Requests/SaveSettingsSectionRequest.php:17,22` — drop `email` section if it only carried templates; drop `brandLogoDataUrl` 3MB rule.
- `backend/app/Services/Settings/PublicSettingsService` (or `SettingsController::publicSettings`) — ensure no sensitive config leaks.

**Backend — create:**
- `backend/app/Services/Settings/SettingResolver.php` — single DB-first-then-default resolver (extracted from `AuthSecuritySettings::resolve` so non-auth settings share it). Used by claim TTL + upload size + dup policy.
- `backend/database/migrations/2026_07_07_000001_seed_live_settings_defaults.php` — backfill rows for the 8 live keys (idempotent via `updateOrCreate`).
- `backend/database/migrations/2026_07_07_000002_drop_placebo_settings_rows.php` — delete the 15 placebo rows from `system_settings`.
- `backend/tests/Feature/Settings/SettingsTruthTest.php` — per-setting behavior tests.

**Frontend — modify:**
- `frontend/app/pages/admin/settings.vue` — remove SMTP panel (lines ~266, ~1503); remove 15 placebo field UI; remove dead email-template editor; logo upload → file API.
- `frontend/app/pages/maintenance.vue` — DELETE.
- `frontend/app/stores/theming.store.ts:33` — `brandLogoDataUrl?: string|null` → logo URL field.
- `frontend/app/app.vue:39` — favicon reads new logo URL.

**Docs — modify:**
- `docs/06-api-reference.md` — remove SMTP endpoints; document remaining settings keys + consumers.

---

## Task 1: SettingResolver primitive + live-key registration

**Files:**
- Create: `backend/app/Services/Settings/SettingResolver.php`
- Modify: `backend/app/Services/Auth/AuthSecuritySettings.php:44-53`
- Test: `backend/tests/Unit/Settings/SettingResolverTest.php`

**Interfaces:**
- Consumes: `App\Models\SystemSetting::findByKey()` (existing, backend/app/Models/SystemSetting.php:26)
- Produces: `SettingResolver::get(string $key, mixed $default): mixed` — DB-first-then-default. `AuthSecuritySettings::resolve()` delegates to it.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\SettingResolver;
use Tests\TestCase;

class SettingResolverTest extends TestCase
{
    public function test_returns_default_when_no_row_exists(): void
    {
        $resolver = app(SettingResolver::class);

        $this->assertSame(15, $resolver->get('support_claim_ttl', 15));
    }

    public function test_returns_db_value_when_row_exists(): void
    {
        SystemSetting::create([
            'key' => 'support_claim_ttl',
            'value' => 30,
        ]);

        $resolver = app(SettingResolver::class);

        $this->assertSame(30, $resolver->get('support_claim_ttl', 15));
    }

    public function test_db_value_null_falls_back_to_default(): void
    {
        SystemSetting::create([
            'key' => 'support_claim_ttl',
            'value' => null,
        ]);

        $resolver = app(SettingResolver::class);

        $this->assertSame(15, $resolver->get('support_claim_ttl', 15));
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Unit/Settings/SettingResolverTest.php
```
Expected: FAIL — `Class App\Services\Settings\SettingResolver not found`.

- [ ] **Step 3: Implement SettingResolver**

```php
<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;

class SettingResolver
{
    /**
     * Resolve a scalar setting value: DB row first, fallback to default.
     * Null DB values fall back to the default (treats unsetting as "use default").
     */
    public function get(string $key, mixed $default): mixed
    {
        $stored = SystemSetting::findByKey($key);

        if ($stored !== null && $stored->value !== null) {
            return $stored->value;
        }

        return $default;
    }
}
```

- [ ] **Step 4: Wire AuthSecuritySettings to delegate**

Refactor `AuthSecuritySettings::resolve()` to delegate:

```php
public function __construct(
    private readonly \App\Services\Settings\SettingResolver $settings,
) {}

private function resolve(string $settingKey, string $configKey): mixed
{
    $stored = SystemSetting::findByKey($settingKey);

    if ($stored !== null && $stored->value !== null) {
        return $stored->value;
    }

    return config($configKey);
}
```

(Keep the config fallback inside AuthSecuritySettings for now — the auth keys still have env defaults that other tooling may set. The resolver is used for the non-auth keys in later tasks. Do NOT remove config fallbacks from auth settings in this task — that's Task 7 once rows are seeded.)

- [ ] **Step 5: Bind resolver as singleton (cache-friendly later)**

In `backend/app/Providers/AppServiceProvider.php` `register()`:

```php
$this->app->singleton(\App\Services\Settings\SettingResolver::class);
```

- [ ] **Step 6: Run test — verify pass**

```bash
php artisan test tests/Unit/Settings/SettingResolverTest.php
```
Expected: PASS, 3 tests.

- [ ] **Step 7: Run AuthSecuritySettings consumers to confirm no regression**

```bash
php artisan test --filter=AuthSecuritySettings
php artisan test tests/Feature/Auth/
```
Expected: existing auth tests still pass (no behavior change — resolve still DB-first-then-config).

- [ ] **Step 8: Commit**

```bash
git add backend/app/Services/Settings/SettingResolver.php backend/app/Services/Auth/AuthSecuritySettings.php backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Settings/SettingResolverTest.php
git commit -m "refactor(settings): extract SettingResolver primitive for DB-first resolution"
```

---

## Task 2: Remove the 15 placebo settings (backend)

**Files:**
- Modify: `backend/app/Services/Settings/AdminSettingsService.php:13-62` (DEFAULTS + VALIDATION_RULES)
- Modify: `backend/app/Http/Controllers/Api/AdminSettingsController.php` (drop any dead-field surfacing)
- Create: `backend/database/migrations/2026_07_07_000002_drop_placebo_settings_rows.php`
- Test: `backend/tests/Feature/Settings/PlaceboSettingsRemovedTest.php`

**Interfaces:**
- Consumes: `AdminSettingsService::DEFAULTS` (existing)
- Produces: trimmed DEFAULTS with only the 8 live keys remaining: `support_claim_ttl`, `pdf_upload_size_limit`, `login_lockout_attempts`, `login_lockout_duration`, `mfa_required`, `duplicate_invoice_policy`, `trusted_device_ttl_hours`, `step_up_window_minutes`.

**The 15 keys to remove** (from DEFAULTS + VALIDATION_RULES + migration deletes their rows):
`voting_session_timeout`, `support_committee_size`, `executive_committee_size`, `minimum_quorum`, `review_timeout_hours`, `secret_voting`, `director_tiebreak`, `notifications_phase_1_enabled`, `search_phase_1_enabled`, `customs_print_preview_enabled`, `password_expiry_90_days`, `lockout_after_5_attempts`, `encrypt_uploads_aes256`, `log_all_audit`, `allow_external_access`.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\AdminSettingsService;
use Tests\TestCase;

class PlaceboSettingsRemovedTest extends TestCase
{
    public function test_defaults_contain_only_live_keys(): void
    {
        $defaults = app(AdminSettingsService::class)->getDefaults();

        $live = [
            'support_claim_ttl', 'pdf_upload_size_limit',
            'login_lockout_attempts', 'login_lockout_duration',
            'mfa_required', 'duplicate_invoice_policy',
            'trusted_device_ttl_hours', 'step_up_window_minutes',
        ];

        $this->assertSame($live, array_keys($defaults));
    }

    public function test_placebo_keys_absent(): void
    {
        $defaults = app(AdminSettingsService::class)->getDefaults();

        $placebo = [
            'voting_session_timeout', 'support_committee_size', 'executive_committee_size',
            'minimum_quorum', 'review_timeout_hours', 'secret_voting', 'director_tiebreak',
            'notifications_phase_1_enabled', 'search_phase_1_enabled', 'customs_print_preview_enabled',
            'password_expiry_90_days', 'lockout_after_5_attempts', 'encrypt_uploads_aes256',
            'log_all_audit', 'allow_external_access',
        ];

        foreach ($placebo as $key) {
            $this->assertArrayNotHasKey($key, $defaults, "Placebo key $key must be removed.");
        }
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Settings/PlaceboSettingsRemovedTest.php
```
Expected: FAIL — DEFAULTS still has 15 placebo keys.

- [ ] **Step 3: Trim DEFAULTS + VALIDATION_RULES**

In `AdminSettingsService.php`, replace `DEFAULTS` const with:

```php
private const DEFAULTS = [
    'support_claim_ttl' => 15,
    'pdf_upload_size_limit' => 10,
    'login_lockout_attempts' => 5,
    'login_lockout_duration' => 15,
    'mfa_required' => false,
    'duplicate_invoice_policy' => 'warn',
    'trusted_device_ttl_hours' => 24,
    'step_up_window_minutes' => 10,
];
```

**`mfa_required => false`** preserves the current effective posture (`config('auth_security.mfa_required')=false`); MFA stays opt-in, no behavior change through consolidation.

Replace `VALIDATION_RULES` const with:

```php
private const VALIDATION_RULES = [
    'support_claim_ttl' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
    'pdf_upload_size_limit' => ['min' => 1, 'max' => 50, 'unit' => 'MB'],
    'login_lockout_attempts' => ['min' => 1, 'max' => 20, 'unit' => 'attempts'],
    'login_lockout_duration' => ['min' => 5, 'max' => 60, 'unit' => 'minutes'],
    'mfa_required' => ['type' => 'boolean'],
    'duplicate_invoice_policy' => ['type' => 'enum', 'values' => ['warn', 'block']],
    'trusted_device_ttl_hours' => ['min' => 1, 'max' => 720, 'unit' => 'hours'],
    'step_up_window_minutes' => ['min' => 1, 'max' => 120, 'unit' => 'minutes'],
];
```

- [ ] **Step 4: Create migration to delete placebo rows**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->whereIn('key', [
                'voting_session_timeout', 'support_committee_size', 'executive_committee_size',
                'minimum_quorum', 'review_timeout_hours', 'secret_voting', 'director_tiebreak',
                'notifications_phase_1_enabled', 'search_phase_1_enabled', 'customs_print_preview_enabled',
                'password_expiry_90_days', 'lockout_after_5_attempts', 'encrypt_uploads_aes256',
                'log_all_audit', 'allow_external_access',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Placebo rows intentionally not restored — they had no consumers.
    }
};
```

- [ ] **Step 5: Run test — verify pass**

```bash
php artisan test tests/Feature/Settings/PlaceboSettingsRemovedTest.php
php artisan migrate
```
Expected: PASS, 2 tests.

- [ ] **Step 6: Run AdminSettingsController index to confirm no crash**

```bash
php artisan test --filter=AdminSettings
```
Expected: any controller test referencing removed keys fails — fix the controller to not surface removed keys (drop from `index()`/`getSecurityPolicies()` output). If `getSecurityPolicies()` returns committee/quorum values, remove that method or trim it.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint app/Services/Settings/AdminSettingsService.php app/Http/Controllers/Api/AdminSettingsController.php database/migrations/2026_07_07_000002_drop_placebo_settings_rows.php
git add backend/app/Services/Settings/AdminSettingsService.php backend/app/Http/Controllers/Api/AdminSettingsController.php backend/database/migrations/2026_07_07_000002_drop_placebo_settings_rows.php backend/tests/Feature/Settings/PlaceboSettingsRemovedTest.php
git commit -m "refactor(settings): remove 15 placebo settings without runtime consumers"
```

---

## Task 3: Wire claim TTL to DB setting

**Files:**
- Modify: `backend/app/Services/Workflow/EngineClaimService.php:17-19`
- Test: `backend/tests/Feature/Workflow/ClaimTtlSettingTest.php`

**Interfaces:**
- Consumes: `App\Services\Settings\SettingResolver::get('support_claim_ttl', 15)`
- Produces: `EngineClaimService::ttlMinutes(): int` reads DB (was config-only).

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Workflow;

use App\Models\SystemSetting;
use App\Services\Workflow\EngineClaimService;
use Tests\TestCase;

class ClaimTtlSettingTest extends TestCase
{
    public function test_ttl_reads_db_setting(): void
    {
        SystemSetting::create(['key' => 'support_claim_ttl', 'value' => 30]);

        $service = app(EngineClaimService::class);

        $reflection = new \ReflectionMethod($service, 'ttlMinutes');
        $reflection->setAccessible(true);

        $this->assertSame(30, $reflection->invoke($service));
    }

    public function test_ttl_falls_back_to_default_without_row(): void
    {
        $service = app(EngineClaimService::class);

        $reflection = new \ReflectionMethod($service, 'ttlMinutes');
        $reflection->setAccessible(true);

        $this->assertSame(15, $reflection->invoke($service));
    }
}
```

- [ ] **Step 2: Run test — verify fail (first passes by accident since default=15; the 30 test fails)**

```bash
php artisan test tests/Feature/Workflow/ClaimTtlSettingTest.php
```
Expected: `test_ttl_reads_db_setting` FAILS (returns 15, config fallback).

- [ ] **Step 3: Inject SettingResolver into EngineClaimService**

```php
public function __construct(
    private readonly \App\Services\Settings\SettingResolver $settings,
) {}

private function ttlMinutes(): int
{
    return (int) $this->settings->get('support_claim_ttl', 15);
}
```

(Remove the `config('workflow.support_claim_ttl_minutes', 15)` line.)

- [ ] **Step 4: Run test — verify pass**

```bash
php artisan test tests/Feature/Workflow/ClaimTtlSettingTest.php
```
Expected: PASS, 2 tests.

- [ ] **Step 5: Regression — claim flow still works**

```bash
php artisan test --filter=Claim
```
Expected: existing claim tests pass (TTL still 15 by default).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint app/Services/Workflow/EngineClaimService.php
git add backend/app/Services/Workflow/EngineClaimService.php backend/tests/Feature/Workflow/ClaimTtlSettingTest.php
git commit -m "feat(settings): wire support claim TTL to DB setting"
```

---

## Task 4: Wire PDF upload size limit to DB setting (5 literals)

**Files:**
- Modify: `backend/app/Http/Requests/UploadSwiftRequest.php:16,19,20`
- Modify: `backend/app/Http/Requests/UploadDocumentRequest.php:15`
- Modify: `backend/app/Http/Requests/FxConfirmationUploadRequest.php:25`
- Modify: `backend/app/Http/Controllers/Api/EngineRequestDocumentController.php:37,171`
- Create: `backend/app/Support/UploadSizeLimit.php`
- Test: `backend/tests/Feature/Documents/UploadSizeLimitSettingTest.php`

**Interfaces:**
- Consumes: `App\Services\Settings\SettingResolver::get('pdf_upload_size_limit', 10)` (MB)
- Produces: `UploadSizeLimit::maxKilobytes(): int` — shared KB value for all upload validations.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Documents;

use App\Models\SystemSetting;
use App\Support\UploadSizeLimit;
use Tests\TestCase;

class UploadSizeLimitSettingTest extends TestCase
{
    public function test_default_is_10mb(): void
    {
        $this->assertSame(10 * 1024, app(UploadSizeLimit::class)->maxKilobytes());
    }

    public function test_reads_db_setting(): void
    {
        SystemSetting::create(['key' => 'pdf_upload_size_limit', 'value' => 25]);

        $this->assertSame(25 * 1024, app(UploadSizeLimit::class)->maxKilobytes());
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Documents/UploadSizeLimitSettingTest.php
```
Expected: FAIL — `UploadSizeLimit` not found.

- [ ] **Step 3: Create UploadSizeLimit support class**

```php
<?php

namespace App\Support;

use App\Services\Settings\SettingResolver;

class UploadSizeLimit
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * Maximum upload size in kilobytes (for Laravel `max:` validation rule).
     * DB setting is in MB; convert to KB.
     */
    public function maxKilobytes(): int
    {
        $mb = (int) $this->settings->get('pdf_upload_size_limit', 10);

        return $mb * 1024;
    }
}
```

- [ ] **Step 4: Run test — verify pass**

```bash
php artisan test tests/Feature/Documents/UploadSizeLimitSettingTest.php
```
Expected: PASS, 2 tests.

- [ ] **Step 5: Replace the 5 hardcoded literals**

Form requests: replace `'mimes:pdf|max:10240'`-style rules with a dynamic value. In each form request, inject `UploadSizeLimit` via constructor (form requests resolve from container, so constructor injection works) and reference in `rules()`:

```php
public function __construct(
    private readonly \App\Support\UploadSizeLimit $uploadSizeLimit,
) {}

public function rules(): array
{
    $maxKb = $this->uploadSizeLimit->maxKilobytes();

    return [
        // ... existing rules, replacing max:10240 with max:{$maxKb}
        'file' => 'required|file|mimes:pdf|max:' . $maxKb,
    ];
}
```

Apply the same pattern to all 5 sites:
- `UploadSwiftRequest.php` (lines 16,19,20 — 3 occurrences)
- `UploadDocumentRequest.php` (line 15)
- `FxConfirmationUploadRequest.php` (line 25)
- `EngineRequestDocumentController.php` (lines 37,171 — inline validation)

For the controller inline validations, inject `UploadSizeLimit` into the controller constructor and use `$this->uploadSizeLimit->maxKilobytes()`.

- [ ] **Step 6: Run upload tests**

```bash
php artisan test --filter=Upload
php artisan test tests/Feature/Documents/UploadSizeLimitSettingTest.php
```
Expected: PASS. Existing upload tests use files under 10MB so still pass at default; the new test covers the DB-driven change.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint app/Support/UploadSizeLimit.php app/Http/Requests/UploadSwiftRequest.php app/Http/Requests/UploadDocumentRequest.php app/Http/Requests/FxConfirmationUploadRequest.php app/Http/Controllers/Api/EngineRequestDocumentController.php
git add backend/app/Support/UploadSizeLimit.php backend/app/Http/Requests/ backend/app/Http/Controllers/Api/EngineRequestDocumentController.php backend/tests/Feature/Documents/UploadSizeLimitSettingTest.php
git commit -m "feat(settings): drive PDF upload size limit from DB setting"
```

---

## Task 5: Wire duplicate invoice policy + readAll (dup policy only — readAll is WP-12)

**Files:**
- Modify: `backend/app/Services/Workflow/DuplicateInvoiceChecker.php`
- Modify: `backend/app/Http/Controllers/Api/EngineRequestController.php` (consumer of `check()`)
- Test: `backend/tests/Feature/Workflow/DuplicateInvoicePolicyTest.php`

**Interfaces:**
- Consumes: `App\Services\Settings\SettingResolver::get('duplicate_invoice_policy', 'warn')`
- Produces: `DuplicateInvoiceChecker::check()` returns payload augmented with `'severity' => 'warn'|'block'`. When `block`, controller returns 422 `DUPLICATE_INVOICE_BLOCKED`.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Workflow;

use App\Models\SystemSetting;
use App\Services\Workflow\DuplicateInvoiceChecker;
use Database\Factories\EngineRequestFactory;
use Tests\TestCase;

class DuplicateInvoicePolicyTest extends TestCase
{
    public function test_warn_policy_returns_detection_with_warn_severity(): void
    {
        EngineRequestFactory::new()->create([
            'invoice_number_normalized' => 'INV-001',
            'status' => 'ACTIVE',
        ]);

        $checker = app(DuplicateInvoiceChecker::class);
        $result = $checker->check('INV-001');

        $this->assertNotNull($result);
        $this->assertSame('warn', $result['severity']);
    }

    public function test_block_policy_returns_block_severity(): void
    {
        EngineRequestFactory::new()->create([
            'invoice_number_normalized' => 'INV-002',
            'status' => 'ACTIVE',
        ]);

        SystemSetting::create(['key' => 'duplicate_invoice_policy', 'value' => 'block']);

        $checker = app(DuplicateInvoiceChecker::class);
        $result = $checker->check('INV-002');

        $this->assertSame('block', $result['severity']);
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Workflow/DuplicateInvoicePolicyTest.php
```
Expected: FAIL — no `severity` key in payload.

- [ ] **Step 3: Inject SettingResolver into DuplicateInvoiceChecker**

```php
use App\Services\Settings\SettingResolver;

class DuplicateInvoiceChecker
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function check(string $invoiceNumber, ?int $excludeRequestId = null): ?array
    {
        // ... existing detection logic ...
        if ($duplicates->isEmpty()) {
            return null;
        }

        return [
            'code' => 'DUPLICATE_INVOICE',
            'severity' => (string) $this->settings->get('duplicate_invoice_policy', 'warn'),
            'message' => 'Invoice number matches existing active request(s).',
            'duplicates' => $duplicates->map(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'bank_id' => $r->bank_id,
            ])->all(),
        ];
    }
}
```

- [ ] **Step 4: Controller honors block severity**

In `EngineRequestController` where `check()` result is consumed (lines ~66, ~296): if `$result['severity'] === 'block'`, return 422 with `DUPLICATE_INVOICE_BLOCKED` before persisting. If `warn`, continue (the existing `afterDuplicateInvoice()` notification path).

- [ ] **Step 5: Run test — verify pass**

```bash
php artisan test tests/Feature/Workflow/DuplicateInvoicePolicyTest.php
php artisan test --filter=DuplicateInvoice
```
Expected: PASS.

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint app/Services/Workflow/DuplicateInvoiceChecker.php app/Http/Controllers/Api/EngineRequestController.php
git add backend/app/Services/Workflow/DuplicateInvoiceChecker.php backend/app/Http/Controllers/Api/EngineRequestController.php backend/tests/Feature/Workflow/DuplicateInvoicePolicyTest.php
git commit -m "feat(settings): honor duplicate invoice policy warn/block from DB setting"
```

---

## Task 6: Register login_lockout_attempts + trusted_device_ttl_hours + step_up_window_minutes in DEFAULTS

These keys are read by `AuthSecuritySettings` but `login_lockout_attempts`/`trusted_device_ttl_hours`/`step_up_window_minutes` are NOT in `AdminSettingsService::DEFAULTS` (so no admin row is ever created → always falls to config). Task 2 added them to DEFAULTS; this task seeds the DB rows so DB becomes truth.

**Files:**
- Create: `backend/database/migrations/2026_07_07_000001_seed_live_settings_defaults.php`
- Test: `backend/tests/Feature/Settings/LiveSettingsSeededTest.php`

**Interfaces:**
- Consumes: `AdminSettingsService::DEFAULTS` (now 8 keys after Task 2)
- Produces: `system_settings` rows for all 8 live keys with their default values.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\AdminSettingsService;
use Tests\TestCase;

class LiveSettingsSeededTest extends TestCase
{
    public function test_all_live_keys_have_db_rows(): void
    {
        $this->artisan('migrate');

        $defaults = app(AdminSettingsService::class)->getDefaults();

        foreach (array_keys($defaults) as $key) {
            $this->assertNotNull(
                SystemSetting::findByKey($key),
                "Live setting $key must have a seeded DB row."
            );
        }
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Settings/LiveSettingsSeededTest.php
php artisan migrate
```
Expected: FAIL — no rows seeded.

- [ ] **Step 3: Create seed migration (idempotent)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\Settings\AdminSettingsService;
use App\Models\SystemSetting;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = app(AdminSettingsService::class)->getDefaults();

        foreach ($defaults as $key => $value) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    public function down(): void
    {
        // Rows intentionally not dropped on rollback — they hold admin edits.
    }
};
```

- [ ] **Step 4: Run test — verify pass**

```bash
php artisan migrate
php artisan test tests/Feature/Settings/LiveSettingsSeededTest.php
```
Expected: PASS.

- [ ] **Step 5: Regression — lockout/MFA still work**

```bash
php artisan test --filter=Lockout
php artisan test tests/Feature/Auth/
```
Expected: existing auth tests pass (values unchanged — defaults match config: 5/15/24/10/true/warn).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint database/migrations/2026_07_07_000001_seed_live_settings_defaults.php
git add backend/database/migrations/2026_07_07_000001_seed_live_settings_defaults.php backend/tests/Feature/Settings/LiveSettingsSeededTest.php
git commit -m "feat(settings): seed DB rows for all live settings defaults"
```

---

## Task 7: Consolidate mfa_required to single switch (remove config OR)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/AuthController.php:460-462` (`mfaRequiredFor()`)
- Test: `backend/tests/Feature/Auth/MfaSingleSwitchTest.php`

**Interfaces:**
- Consumes: `App\Services\Auth\AuthSecuritySettings::mfaRequired()` (DB-first now that row is seeded)
- Produces: `AuthController::mfaRequiredFor()` reads ONLY `AuthSecuritySettings::mfaRequired()` + `hasTotpConfigured()` — removes the `config('mfa.enabled')` OR.

**Important:** DB default for `mfa_required` is `false` (DEFAULTS, set to match `config('auth_security.mfa_required')=false`). After seeding (Task 6) + consolidation (Task 7), MFA stays OFF by default — no posture change. Admin enables MFA via the API when ready.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use Tests\TestCase;

class MfaSingleSwitchTest extends TestCase
{
    public function test_mfa_required_false_disables_gate(): void
    {
        SystemSetting::where('key', 'mfa_required')->update(['value' => false]);

        $this->assertFalse(app(\App\Services\Auth\AuthSecuritySettings::class)->mfaRequired());
    }

    public function test_config_mfa_enabled_no_longer_overrides_when_db_false(): void
    {
        config(['mfa.enabled' => true]);
        SystemSetting::where('key', 'mfa_required')->update(['value' => false]);

        // After consolidation, config('mfa.enabled') is NOT consulted by mfaRequiredFor.
        $reflection = new \ReflectionMethod(\App\Http\Controllers\Api\AuthController::class, 'mfaRequiredFor');
        $reflection->setAccessible(true);

        $controller = app(\App\Http\Controllers\Api\AuthController::class);
        $user = \Database\Factories\UserFactory::new()->create();

        $this->assertFalse($reflection->invoke($controller, $user));
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Auth/MfaSingleSwitchTest.php
```
Expected: `test_config_mfa_enabled_no_longer_overrides_when_db_false` FAILS — config still OR'd in.

- [ ] **Step 3: Remove the config OR in mfaRequiredFor**

In `AuthController::mfaRequiredFor()` (~line 460-462), remove the `config('mfa.enabled')` term. Keep `AuthSecuritySettings::mfaRequired()` (DB) + `hasTotpConfigured()`.

- [ ] **Step 4: Run test — verify pass**

```bash
php artisan test tests/Feature/Auth/MfaSingleSwitchTest.php
```
Expected: PASS.

- [ ] **Step 5: Regression — MFA flow still functional**

```bash
php artisan test tests/Feature/Auth/
```
Expected: existing auth tests pass (DB row now seeded by Task 6). Some tests may have asserted `config('mfa.enabled')` behavior — update those to set the DB setting instead (do NOT disable the rule; fix the test to use the new truth source).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint app/Http/Controllers/Api/AuthController.php
git add backend/app/Http/Controllers/Api/AuthController.php backend/tests/Feature/Auth/MfaSingleSwitchTest.php
git commit -m "refactor(settings): consolidate MFA to single mfa_required DB switch"
```

---

## Task 8: Remove SMTP panel (env-only mail) + maintenance page

**Files:**
- Modify: `backend/app/Http/Controllers/Api/AdminSettingsController.php` (remove `getSmtp`, `updateSmtp`, `testEmail`)
- Modify: `backend/app/Services/Settings/AdminSettingsService.php` (remove `getSmtpSettings`, `updateSmtpSettings`)
- Modify: `backend/routes/api.php:280-282` (remove SMTP + test-email routes)
- Modify: `backend/app/Services/Settings/SystemSettingsService.php` (remove `getEmailTemplates` consumer if present)
- Delete: `frontend/app/pages/admin/settings.vue` SMTP panel section (lines ~266, ~1503)
- Delete: `frontend/app/pages/maintenance.vue`
- Modify: `frontend/app/app.vue` (remove maintenance error route if it references the page)
- Test: `backend/tests/Feature/Settings/SmtpPanelRemovedTest.php`

**Decision (locked):** Runtime mailer reads `.env` only (`config/mail.php`). DB SMTP rows are placebo. Remove the editable panel; keep read-only mail diagnostics as a doc note (no fake test-email endpoint). Maintenance: no backend toggle exists → remove the frontend page.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

class SmtpPanelRemovedTest extends TestCase
{
    public function test_smtp_routes_absent(): void
    {
        $routes = collect(\Route::getRoutes()->get('PUT'))
            ->flatMap(fn ($methods, $uri) => [$uri]);

        $this->assertFalse(in_array('api/admin/settings/smtp', $this->allUris()));
        $this->assertFalse(collect($this->allUris())->contains('api/admin/settings/email/test'));
    }

    private function allUris(): array
    {
        return collect(\Route::getRoutes())->map(fn ($r) => $r->uri())->toArray();
    }

    public function test_smtp_methods_absent_from_service(): void
    {
        $this->assertFalse(
            method_exists(\App\Services\Settings\AdminSettingsService::class, 'getSmtpSettings'),
            'SMTP settings methods must be removed (env-only mail).'
        );
        $this->assertFalse(
            method_exists(\App\Services\Settings\AdminSettingsService::class, 'updateSmtpSettings')
        );
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Settings/SmtpPanelRemovedTest.php
```
Expected: FAIL — methods/routes still exist.

- [ ] **Step 3: Remove backend SMTP routes + controller methods + service methods**

In `routes/api.php`, delete lines 280-282:
```php
Route::get('admin/settings/smtp', [AdminSettingsController::class, 'getSmtp']);
Route::put('admin/settings/smtp', [AdminSettingsController::class, 'updateSmtp']);
Route::post('admin/settings/email/test', [AdminSettingsController::class, 'testEmail'])->middleware('throttle:5,1');
```

Delete the `getSmtp()`, `updateSmtp()`, `testEmail()` methods from `AdminSettingsController`.

Delete `getSmtpSettings()` and `updateSmtpSettings()` from `AdminSettingsService`.

- [ ] **Step 4: Run test — verify pass**

```bash
php artisan test tests/Feature/Settings/SmtpPanelRemovedTest.php
```
Expected: PASS.

- [ ] **Step 5: Delete frontend SMTP panel + maintenance page**

In `frontend/app/pages/admin/settings.vue`, remove the SMTP panel template block (around line 266 `host: 'smtp.cby.gov.ye'` default and the SMTP inputs ~line 1503) + any SMTP state/fetch logic.

Delete `frontend/app/pages/maintenance.vue`.

Check `frontend/app/app.vue` for a maintenance error handler — if it navigates to `/maintenance`, remove that handler or replace with the standard Nuxt error page.

- [ ] **Step 6: Frontend verification**

```bash
cd frontend && pnpm typecheck && pnpm lint
```
Expected: no new errors in touched files.

- [ ] **Step 7: Commit**

```bash
git add backend/routes/api.php backend/app/Http/Controllers/Api/AdminSettingsController.php backend/app/Services/Settings/AdminSettingsService.php backend/tests/Feature/Settings/SmtpPanelRemovedTest.php frontend/app/pages/admin/settings.vue
git rm frontend/app/pages/maintenance.vue
git commit -m "refactor(settings): remove placebo SMTP panel and maintenance page"
```

---

## Task 9: Logo file storage (migrate base64 blob to storage)

**Files:**
- Modify: `backend/app/Services/Settings/SystemSettingsService.php:23-33,49-55,89` (logo handling)
- Modify: `backend/app/Http/Requests/SaveSettingsSectionRequest.php:21-22` (drop 3MB rule)
- Create: `backend/app/Services/Settings/LogoStorageService.php`
- Create: `backend/database/migrations/2026_07_07_000003_migrate_logo_to_storage.php`
- Test: `backend/tests/Feature/Settings/LogoStorageTest.php`

**Interfaces:**
- Consumes: Laravel `Storage` disk (public/private per existing config)
- Produces: `LogoStorageService::store(UploadedFile $file): string` (returns URL/path), `LogoStorageService::url(?string $path): ?string`. Settings store the path, not base64.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\LogoStorageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LogoStorageTest extends TestCase
{
    public function test_logo_stored_as_file_not_base64(): void
    {
        \Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        $path = app(LogoStorageService::class)->store($file);

        $this->assertStringStartsWith('logos/', $path);
        \Storage::disk('public')->assertExists($path);
    }

    public function test_public_settings_expose_logo_url_not_dataurl(): void
    {
        \Storage::fake('public');
        SystemSetting::updateOrCreate(
            ['key' => 'settings.branding'],
            ['value' => ['brandLogoPath' => 'logos/abc.png', 'brandColor' => '#0066cc']],
        );

        $public = app(\App\Services\Settings\SystemSettingsService::class)->getPublicSettings();

        $this->assertStringNotContainsString('data:image', $public['branding']['brandLogoUrl'] ?? '');
        $this->assertNotEmpty($public['branding']['brandLogoUrl']);
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Settings/LogoStorageTest.php
```
Expected: FAIL — `LogoStorageService` not found; `brandLogoUrl` not exposed.

- [ ] **Step 3: Create LogoStorageService**

```php
<?php

namespace App\Services\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LogoStorageService
{
    private const DISK = 'public';
    private const DIRECTORY = 'logos';

    public function store(UploadedFile $file): string
    {
        $this->validateType($file);

        return $file->store(self::DIRECTORY, self::DISK);
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Existing default static emblem (no upload yet) — serve as-is.
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    private function validateType(UploadedFile $file): void
    {
        $allowed = ['image/png', 'image/svg+xml', 'image/jpeg', 'image/webp'];

        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw new \InvalidArgumentException('Logo must be PNG, SVG, JPEG, or WebP.');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Logo must be under 2MB.');
        }
    }
}
```

- [ ] **Step 4: Wire SystemSettingsService to use path + expose URL**

In `SystemSettingsService::saveSection()` branding branch: if `brandLogoFile` uploaded, call `LogoStorageService::store()`, write `brandLogoPath` to the blob, drop `brandLogoDataUrl`.

In `getPublicSettings()`: return `brandLogoUrl => $logoStorage->url($branding['brandLogoPath'] ?? null)` instead of `brandLogoDataUrl`.

- [ ] **Step 5: Update SaveSettingsSectionRequest**

Drop `data.brandLogoDataUrl` 3MB rule. Add `data.brandLogoFile => sometimes|nullable|file|mimes:png,svg,jpeg,webp|max:2048`.

- [ ] **Step 6: Create migration to migrate existing base64 → file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $branding = SystemSetting::getValueByKey('settings.branding', []);

        $dataUrl = $branding['brandLogoDataUrl'] ?? null;
        if ($dataUrl === null || ! str_starts_with($dataUrl, 'data:')) {
            return; // No base64 to migrate.
        }

        // Decode and store as a file.
        if (preg_match('/^data:(image\/[a-z+.-]+);base64,(.+)$/', $dataUrl, $m)) {
            $ext = str_contains($m[1], 'svg') ? 'svg' : (str_contains($m[1], 'png') ? 'png' : 'jpg');
            $name = 'logos/migrated-' . uniqid() . '.' . $ext;
            Storage::disk('public')->put($name, base64_decode($m[2]));
            $branding['brandLogoPath'] = $name;
            unset($branding['brandLogoDataUrl']);
            SystemSetting::where('key', 'settings.branding')->update(['value' => $branding]);
        }
    }

    public function down(): void {}
};
```

- [ ] **Step 7: Run test — verify pass**

```bash
php artisan test tests/Feature/Settings/LogoStorageTest.php
php artisan migrate
```
Expected: PASS.

- [ ] **Step 8: Frontend — theming store + app.vue favicon**

Update `frontend/app/stores/theming.store.ts:33`: `brandLogoDataUrl?: string|null` → `brandLogoUrl?: string|null`. Update `frontend/app/app.vue:39` favicon to read `brandLogoUrl`.

- [ ] **Step 9: Format + commit**

```bash
vendor/bin/pint app/Services/Settings/LogoStorageService.php app/Services/Settings/SystemSettingsService.php app/Http/Requests/SaveSettingsSectionRequest.php database/migrations/2026_07_07_000003_migrate_logo_to_storage.php
cd frontend && pnpm lint
git add backend/app/Services/Settings/LogoStorageService.php backend/app/Services/Settings/SystemSettingsService.php backend/app/Http/Requests/SaveSettingsSectionRequest.php backend/database/migrations/2026_07_07_000003_migrate_logo_to_storage.php backend/tests/Feature/Settings/LogoStorageTest.php frontend/app/stores/theming.store.ts frontend/app/app.vue
git commit -m "feat(settings): store brand logo as file instead of base64 blob"
```

---

## Task 10: Cache read-through for settings + remove dead email-template blob

**Files:**
- Modify: `backend/app/Services/Settings/SettingResolver.php` (add cache)
- Modify: `backend/app/Services/Settings/AdminSettingsService.php:248` (invalidate resolver cache)
- Modify: `backend/app/Services/Settings/SystemSettingsService.php:134-152` (remove `settings.email.templates` write)
- Modify: `backend/app/Http/Requests/SaveSettingsSectionRequest.php:17` (remove `email` section if only carried templates)
- Test: `backend/tests/Feature/Settings/SettingsCacheTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Cache`
- Produces: `SettingResolver::get()` cached under `setting:{key}`; `AdminSettingsService::update()` + `reset()` forget the key. Dead `email` section gone.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\SettingResolver;
use Tests\TestCase;

class SettingsCacheTest extends TestCase
{
    public function test_cache_invalidated_on_update(): void
    {
        SystemSetting::create(['key' => 'support_claim_ttl', 'value' => 15]);

        $resolver = app(SettingResolver::class);
        $this->assertSame(15, $resolver->get('support_claim_ttl', 15)); // populates cache

        SystemSetting::where('key', 'support_claim_ttl')->update(['value' => 30]);

        // Without invalidation, cache would still return 15. AdminSettingsService::update
        // forgets the cache, so a fresh resolve returns 30.
        app(\App\Services\Settings\AdminSettingsService::class)
            ->update(auth()->user() ?? \Database\Factories\UserFactory::new()->create(), 'support_claim_ttl', 30);

        $this->assertSame(30, $resolver->get('support_claim_ttl', 15));
    }
}
```

- [ ] **Step 2: Run test — verify fail**

```bash
php artisan test tests/Feature/Settings/SettingsCacheTest.php
```
Expected: FAIL — resolver not cached.

- [ ] **Step 3: Add cache to SettingResolver**

```php
public function get(string $key, mixed $default): mixed
{
    return Cache::remember("setting:{$key}", now()->addHour(), function () use ($key, $default) {
        $stored = SystemSetting::findByKey($key);

        if ($stored !== null && $stored->value !== null) {
            return $stored->value;
        }

        return $default;
    });
}

public function forget(string $key): void
{
    Cache::forget("setting:{$key}");
}
```

- [ ] **Step 4: AdminSettingsService invalidates on update/reset**

In `AdminSettingsService::update()` (~line 127) and `reset()` (~line 148), after writing, call `$this->resolver->forget($key)` (inject `SettingResolver`). Remove the vestigial `invalidateCache()` bullet-mask method (or repoint it to `resolver->forget`).

- [ ] **Step 5: Remove dead email-template blob**

In `SystemSettingsService::normalizeSectionData()` (lines 134-152), remove the `settings.email.templates` write. If the `email` section in `SaveSettingsSectionRequest::rules()` (`:17`) only carried templates, remove `email` from the allowed sections list.

- [ ] **Step 6: Run test — verify pass**

```bash
php artisan test tests/Feature/Settings/SettingsCacheTest.php
php artisan test tests/Feature/Settings/
```
Expected: PASS.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint app/Services/Settings/SettingResolver.php app/Services/Settings/AdminSettingsService.php app/Services/Settings/SystemSettingsService.php app/Http/Requests/SaveSettingsSectionRequest.php
git add backend/app/Services/Settings/ backend/app/Http/Requests/SaveSettingsSectionRequest.php backend/tests/Feature/Settings/SettingsCacheTest.php
git commit -m "perf(settings): cache-through setting reads with update invalidation"
```

---

## Task 11: Public settings safety audit + docs

**Files:**
- Modify: `backend/app/Http/Controllers/Api/SettingsController.php:32` (`publicSettings()`)
- Modify: `docs/06-api-reference.md`
- Test: `backend/tests/Feature/Settings/PublicSettingsSafetyTest.php`

**Interfaces:**
- Produces: `GET /api/settings/public` exposes ONLY general + branding + version stamp. Never security/SMTP/operational config.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

class PublicSettingsSafetyTest extends TestCase
{
    public function test_public_payload_exposes_no_sensitive_config(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertOk();
        $payload = $response->json('data');

        $forbidden = ['smtp', 'login_lockout', 'mfa', 'password', 'secret', 'token', 'claims', 'audit'];
        $json = json_encode($payload);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $json, "Public settings leaked $needle.");
        }
    }

    public function test_public_payload_has_version_stamp(): void
    {
        $response = $this->getJson('/api/settings/public');

        $this->assertArrayHasKey('version', $response->json('data'));
    }
}
```

- [ ] **Step 2: Run test — verify pass/fail baseline**

```bash
php artisan test tests/Feature/Settings/PublicSettingsSafetyTest.php
```
Expected: PASS mostly (current public payload is general+branding). If `version` key missing, add it. Confirm no leak.

- [ ] **Step 3: Add version stamp if absent**

In `SettingsController::publicSettings()` / `SystemSettingsService::getPublicSettings()`, ensure `'version' => config('app.version', '1.0.0')` (or a cache-bust hash of branding updated_at) is in the payload.

- [ ] **Step 4: Update docs/06-api-reference.md**

Remove SMTP endpoints (`GET/PUT /api/admin/settings/smtp`, `POST /api/admin/settings/email/test`). Document the 8 live settings keys with their runtime consumers. Document `GET /api/settings/public` safe payload.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint app/Http/Controllers/Api/SettingsController.php app/Services/Settings/SystemSettingsService.php
git add backend/app/Http/Controllers/Api/SettingsController.php backend/app/Services/Settings/SystemSettingsService.php backend/tests/Feature/Settings/PublicSettingsSafetyTest.php docs/06-api-reference.md
git commit -m "docs(settings): document settings truth surface and remove SMTP from API ref"
```

---

## Task 12: Gate + final review

**Files:** none new.

- [ ] **Step 1: Focused backend suite for WP-11**

```bash
php artisan test tests/Feature/Settings/ tests/Feature/Auth/ tests/Feature/Workflow/ClaimTtlSettingTest.php tests/Feature/Workflow/DuplicateInvoicePolicyTest.php tests/Feature/Documents/UploadSizeLimitSettingTest.php tests/Unit/Settings/
```
Expected: all green. Any failure must be attributed to pre-existing baseline (compare against pre-WP-11 commit) — not WP-11 regressions.

- [ ] **Step 2: Frontend gate**

```bash
cd frontend && pnpm lint && pnpm format:check && pnpm typecheck
```
Expected: only pre-existing debt in untouched files.

- [ ] **Step 3: Generate review package + dispatch final whole-branch reviewer (opus)**

```bash
cd /Users/majedsiefalnasr/.claude-glm/skills/subagent-driven-development && ./scripts/review-package $(git merge-base main HEAD) HEAD
```
Dispatch opus reviewer with the printed package path + global constraints.

- [ ] **Step 4: Apply final-review fixes (one fix subagent for all findings)**

- [ ] **Step 5: Merge to main (--no-ff)**

```bash
git checkout main
git merge --no-ff worktree-wp11-settings-truth -m "feat(settings): merge WP-11 settings truth wave"
```

- [ ] **Step 6: Update wave plan doc + memory**

Mark Wave 5 WP-11 ✅ complete in `docs/superpowers/plans/2026-07-06-implementation-wave-plan.md`. Update `project_dynamic_workflow_engine` memory.

---

## Open questions resolved

1. **ST-3 SMTP:** RESOLVED — remove panel (env-only mail). Task 8.
2. **ST-9 maintenance:** RESOLVED — remove page. Task 8.
3. **ST-2 `password_expiry_90_days`:** stays removed (Task 2). Deferred to a future WP-6 A-3 policy if needed.

## Open questions remaining

1. **MFA default posture:** RESOLVED — `mfa_required=false` (OFF) in DEFAULTS, matches current config. No posture change through consolidation. MFA stays opt-in; admin enables via API.
2. **`login_lockout_attempts` config value (5) vs AGENTS.md "10 consecutive failures":** discrepancy. WP-11 keeps 5 (the live gate value) as DB default to avoid changing posture. Flag for separate hardening story.
