<?php

namespace Tests\Unit\Enums;

use App\Enums\RequestStatus;
use PHPUnit\Framework\TestCase;

class BankReturnedLabelTest extends TestCase
{
    // ─── Story 17-E.4: "Returned to Data Entry" display label ─────────────────

    public function test_bank_returned_label_is_returned_to_data_entry(): void
    {
        $label = RequestStatus::BANK_RETURNED->label();

        $this->assertStringContainsString('Returned to Data Entry', $label);
        $this->assertStringContainsString('أُعيد إلى مدخل البيانات', $label);
    }

    public function test_bank_returned_label_drops_legacy_returned_for_review_wording(): void
    {
        $label = RequestStatus::BANK_RETURNED->label();

        $this->assertStringNotContainsString('Returned for Review', $label);
    }
}
