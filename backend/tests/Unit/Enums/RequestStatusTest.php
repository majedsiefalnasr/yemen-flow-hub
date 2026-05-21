<?php

namespace Tests\Unit\Enums;

use App\Enums\RequestStatus;
use PHPUnit\Framework\TestCase;

class RequestStatusTest extends TestCase
{
    public function test_all_canonical_values_exist(): void
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
            'BANK_RETURNED',
            'SUPPORT_RETURNED',
            'BANK_REJECTED',
        ];

        $actual = array_column(RequestStatus::cases(), 'value');

        $this->assertCount(21, $actual);
        foreach ($expected as $value) {
            $this->assertContains($value, $actual, "Missing canonical status: {$value}");
        }
    }

    public function test_terminal_statuses(): void
    {
        $this->assertTrue(RequestStatus::COMPLETED->isTerminal());
        $this->assertTrue(RequestStatus::EXECUTIVE_REJECTED->isTerminal());
        $this->assertTrue(RequestStatus::CUSTOMS_DECLARATION_ISSUED->isTerminal(), 'CUSTOMS_DECLARATION_ISSUED is treated as immutable per Story 2.1 AC-5');
        $this->assertFalse(RequestStatus::SUPPORT_REJECTED->isTerminal(), 'SUPPORT_REJECTED can be reopened via return_to_entry');
        $this->assertFalse(RequestStatus::DRAFT->isTerminal());
        $this->assertFalse(RequestStatus::SUBMITTED->isTerminal());
        $this->assertFalse(RequestStatus::BANK_RETURNED->isTerminal());
        $this->assertFalse(RequestStatus::SUPPORT_RETURNED->isTerminal());
        $this->assertTrue(RequestStatus::BANK_REJECTED->isTerminal(), 'BANK_REJECTED is a terminal status — no resubmission allowed');
    }

    public function test_editable_statuses(): void
    {
        $this->assertTrue(RequestStatus::DRAFT->isEditable());
        $this->assertTrue(RequestStatus::DRAFT_REJECTED_INTERNAL->isEditable());
        $this->assertTrue(RequestStatus::BANK_RETURNED->isEditable());
        $this->assertTrue(RequestStatus::SUPPORT_RETURNED->isEditable());
        $this->assertFalse(RequestStatus::SUBMITTED->isEditable());
        $this->assertFalse(RequestStatus::COMPLETED->isEditable());
        $this->assertFalse(RequestStatus::BANK_REJECTED->isEditable(), 'BANK_REJECTED is terminal — not editable');
    }

    public function test_bank_returned_has_arabic_and_english_label(): void
    {
        $label = RequestStatus::BANK_RETURNED->label();
        $this->assertStringContainsString('إعادة للمدخل', $label);
        $this->assertStringContainsString('Returned to Intake', $label);
    }

    public function test_bank_rejected_has_arabic_and_english_label(): void
    {
        $label = RequestStatus::BANK_REJECTED->label();
        $this->assertStringContainsString('مرفوض', $label);
        $this->assertStringContainsString('Bank Rejected', $label);
    }

    public function test_from_string_round_trips(): void
    {
        foreach (RequestStatus::cases() as $status) {
            $this->assertSame($status, RequestStatus::from($status->value));
        }
    }
}
