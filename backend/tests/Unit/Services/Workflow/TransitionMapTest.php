<?php

namespace Tests\Unit\Services\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Services\Workflow\TransitionMap;
use PHPUnit\Framework\TestCase;

class TransitionMapTest extends TestCase
{
    // ─── Story 17-E.1: era-gated reviewer reject availability ─────────────────

    public function test_v1_exposes_bank_reject_and_terminal(): void
    {
        $this->assertTrue(TransitionMap::isActionAvailableForVersion('bank_reject', 1));
        $this->assertTrue(TransitionMap::isActionAvailableForVersion('bank_reject_terminal', 1));
    }

    public function test_v2_excludes_bank_reject_and_terminal(): void
    {
        $this->assertFalse(TransitionMap::isActionAvailableForVersion('bank_reject', 2));
        $this->assertFalse(TransitionMap::isActionAvailableForVersion('bank_reject_terminal', 2));
    }

    // ─── Story 17-E.2: era-gated support decision availability ────────────────

    public function test_v1_exposes_support_approve_and_reject_but_not_forward(): void
    {
        $this->assertTrue(TransitionMap::isActionAvailableForVersion('support_approve', 1));
        $this->assertTrue(TransitionMap::isActionAvailableForVersion('support_reject', 1));
        $this->assertFalse(TransitionMap::isActionAvailableForVersion('support_forward_to_executive', 1));
    }

    public function test_v2_exposes_only_support_forward(): void
    {
        $this->assertFalse(TransitionMap::isActionAvailableForVersion('support_approve', 2));
        $this->assertFalse(TransitionMap::isActionAvailableForVersion('support_reject', 2));
        $this->assertTrue(TransitionMap::isActionAvailableForVersion('support_forward_to_executive', 2));
    }

    // ─── Era-agnostic actions resolve for both eras ───────────────────────────

    public function test_era_agnostic_actions_available_for_both_versions(): void
    {
        foreach (['submit', 'bank_approve', 'bank_return_to_intake', 'support_return_to_intake', 'finalize_approved', 'finalize_rejected'] as $action) {
            $this->assertTrue(TransitionMap::isActionAvailableForVersion($action, 1), "$action should be available for v1");
            $this->assertTrue(TransitionMap::isActionAvailableForVersion($action, 2), "$action should be available for v2");
        }
    }

    // ─── AC2 (17-E.1): return_to_data_entry contract reused via bank_return_to_intake

    public function test_bank_return_to_intake_satisfies_return_to_data_entry_contract(): void
    {
        $def = TransitionMap::definitions()['bank_return_to_intake'];

        $this->assertSame([RequestStatus::BANK_REVIEW], $def['from']);
        $this->assertSame(RequestStatus::BANK_RETURNED, $def['to']);
        $this->assertSame([UserRole::BANK_REVIEWER], $def['roles']);
        $this->assertSame(UserRole::DATA_ENTRY, $def['next_owner']);
    }

    public function test_no_duplicate_return_to_data_entry_key(): void
    {
        // The story aliases/reuses bank_return_to_intake rather than adding a parallel key.
        $this->assertArrayNotHasKey('return_to_data_entry', TransitionMap::definitions());
    }

    // ─── AC2 (17-E.2): support_forward_to_executive contract ──────────────────

    public function test_support_forward_to_executive_contract(): void
    {
        $def = TransitionMap::definitions()['support_forward_to_executive'];

        $this->assertSame([RequestStatus::SUPPORT_REVIEW_IN_PROGRESS], $def['from']);
        $this->assertSame(RequestStatus::SUPPORT_APPROVED, $def['to']);
        $this->assertSame([UserRole::SUPPORT_COMMITTEE], $def['roles']);
        // Mirrors support_approve: next owner is SWIFT_OFFICER (SWIFT step preserved).
        $this->assertSame(UserRole::SWIFT_OFFICER, $def['next_owner']);
    }
}
