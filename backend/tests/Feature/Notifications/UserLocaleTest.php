<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ATDD RED PHASE — Story 15.1 Outbox & Delivery Foundation.
 *
 * Guardrail for users.locale (AC6). Default must be 'ar' (Arabic-first).
 * Fails until the add-locale-to-users migration + model exposure exist.
 *
 * @group atdd-15-1
 */
class UserLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** T24 — users.locale column exists. */
    public function test_users_locale_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'locale'));
    }

    /** T24b/T25 — a freshly created user defaults to 'ar'. */
    public function test_new_user_defaults_locale_to_ar(): void
    {
        $user = User::factory()->create();
        $this->assertSame('ar', $user->fresh()->locale, 'users.locale must default to ar.');
    }
}
