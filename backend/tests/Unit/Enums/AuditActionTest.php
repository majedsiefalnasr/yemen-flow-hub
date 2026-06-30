<?php

namespace Tests\Unit\Enums;

use App\Enums\AuditAction;
use PHPUnit\Framework\TestCase;

class AuditActionTest extends TestCase
{
    public function test_login_failed_case_exists(): void
    {
        $values = array_column(AuditAction::cases(), 'value');

        $this->assertContains('LOGIN_FAILED', $values);
    }

    public function test_login_failed_has_label(): void
    {
        $this->assertNotEmpty(AuditAction::LOGIN_FAILED->label());
    }

    public function test_login_login_failed_logout_all_exist(): void
    {
        $values = array_column(AuditAction::cases(), 'value');

        $this->assertContains('LOGIN', $values);
        $this->assertContains('LOGOUT', $values);
        $this->assertContains('LOGIN_FAILED', $values);
    }
}
