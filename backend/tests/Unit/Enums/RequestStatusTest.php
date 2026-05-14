<?php

namespace Tests\Unit\Enums;

use App\Enums\RequestStatus;
use PHPUnit\Framework\TestCase;

class RequestStatusTest extends TestCase
{
    public function test_all_18_canonical_values_exist(): void
    {
        $expected = [
            'DRAFT',
            'DRAFT_REJECTED_INTERNAL',
            'SUBMITTED',
            'BANK_REVIEW',
            'BANK_APPROVED',
            'SUPPORT_REVIEW_PENDING',
            'SUPPORT_REVIEW_IN_PROGRESS',
            'SUPPORT_APPROVED',
            'SUPPORT_REJECTED',
            'WAITING_FOR_SWIFT',
            'SWIFT_UPLOADED',
            'WAITING_FOR_VOTING_OPEN',
            'EXECUTIVE_VOTING_OPEN',
            'EXECUTIVE_VOTING_CLOSED',
            'EXECUTIVE_APPROVED',
            'EXECUTIVE_REJECTED',
            'CUSTOMS_DECLARATION_ISSUED',
            'COMPLETED',
        ];

        $actual = array_column(RequestStatus::cases(), 'value');

        $this->assertCount(18, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing canonical status: {$value}");
        }
    }

    public function test_terminal_statuses(): void
    {
        $this->assertTrue(RequestStatus::COMPLETED->isTerminal());
        $this->assertTrue(RequestStatus::EXECUTIVE_REJECTED->isTerminal());
        $this->assertFalse(RequestStatus::CUSTOMS_DECLARATION_ISSUED->isTerminal(), 'CUSTOMS_DECLARATION_ISSUED has a complete outgoing transition');
        $this->assertFalse(RequestStatus::SUPPORT_REJECTED->isTerminal(), 'SUPPORT_REJECTED can be reopened via return_to_entry');
        $this->assertFalse(RequestStatus::DRAFT->isTerminal());
        $this->assertFalse(RequestStatus::SUBMITTED->isTerminal());
    }

    public function test_editable_statuses(): void
    {
        $this->assertTrue(RequestStatus::DRAFT->isEditable());
        $this->assertTrue(RequestStatus::DRAFT_REJECTED_INTERNAL->isEditable());
        $this->assertFalse(RequestStatus::SUBMITTED->isEditable());
        $this->assertFalse(RequestStatus::COMPLETED->isEditable());
    }

    public function test_from_string_round_trips(): void
    {
        foreach (RequestStatus::cases() as $status) {
            $this->assertSame($status, RequestStatus::from($status->value));
        }
    }
}
