<?php

namespace Tests\Unit\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Services\Workflow\TransitionMap;
use PHPUnit\Framework\TestCase;

class TransitionMapTest extends TestCase
{
    public function test_bank_return_to_intake_entry_exists(): void
    {
        $defs = TransitionMap::definitions();
        $this->assertArrayHasKey('bank_return_to_intake', $defs);
    }

    public function test_bank_return_to_intake_has_correct_from_to_roles_next_owner(): void
    {
        $def = TransitionMap::definitions()['bank_return_to_intake'];
        $this->assertContains(RequestStatus::BANK_REVIEW, $def['from']);
        $this->assertSame(RequestStatus::BANK_RETURNED, $def['to']);
        $this->assertContains(UserRole::BANK_REVIEWER, $def['roles']);
        $this->assertSame(UserRole::DATA_ENTRY, $def['next_owner']);
    }

    public function test_submit_accepts_bank_returned_as_from_status(): void
    {
        $submitDef = TransitionMap::definitions()['submit'];
        $this->assertContains(RequestStatus::BANK_RETURNED, $submitDef['from']);
    }

    public function test_submit_still_accepts_draft_and_draft_rejected_internal(): void
    {
        $submitDef = TransitionMap::definitions()['submit'];
        $this->assertContains(RequestStatus::DRAFT, $submitDef['from']);
        $this->assertContains(RequestStatus::DRAFT_REJECTED_INTERNAL, $submitDef['from']);
    }
}
