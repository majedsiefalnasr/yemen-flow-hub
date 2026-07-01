<?php

namespace Tests\Unit\Enums;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstitutionLabelRebrandTest extends TestCase
{
    private const ARABIC_NAME = 'اللجنة الوطنية لتنظيم وتمويل الواردات';

    private const ENGLISH_NAME = 'The National Committee for Regulating & Financing Imports';

    public function test_pdf_letterhead_views_use_national_committee_identity(): void
    {
        foreach ($this->pdfViews() as $view => $data) {
            $rendered = (string) $this->view($view, $data);

            $this->assertStringContainsString(self::ARABIC_NAME, $rendered);
            $this->assertStringNotContainsString('Yemen Flow Hub', $rendered);
            $this->assertStringNotContainsString('البنك المركزي اليمني', $rendered);
            $this->assertStringNotContainsString('Central Bank of Yemen', $rendered);
        }
    }

    public function test_email_views_and_confidentiality_notice_use_national_committee_identity(): void
    {
        foreach ($this->emailViews() as $view => $data) {
            $rendered = (string) $this->view($view, $data);

            $this->assertStringContainsString(self::ARABIC_NAME, $rendered);
            $this->assertStringNotContainsString('Yemen Flow Hub', $rendered);
            $this->assertStringNotContainsString('البنك المركزي اليمني', $rendered);
            $this->assertStringNotContainsString('Central Bank of Yemen', $rendered);
        }

        $notice = (string) $this->blade('<x-email.confidentiality-notice />');

        $this->assertStringContainsString(self::ARABIC_NAME, $notice);
        $this->assertStringContainsString(self::ENGLISH_NAME, $notice);
        $this->assertStringNotContainsString('البنك المركزي اليمني', $notice);
        $this->assertStringNotContainsString('Central Bank of Yemen', $notice);
    }

    private function pdfViews(): array
    {
        return [
            'pdf.customs-declaration' => [
                'declarationNumber' => 'FX-2026-0001',
                'issuedAt' => Carbon::parse('2026-06-07 10:00:00'),
                'requestModel' => $this->requestModel(),
                'issuer' => (object) ['name' => 'Director'],
            ],
            'pdf.fx-confirmation' => $this->confirmationData(),
            'pdf.confirmation-request' => $this->confirmationData(),
            'pdf.confirmation-request-preview' => $this->confirmationData(),
        ];
    }

    private function emailViews(): array
    {
        $requestModel = $this->requestModel();

        return [
            'emails.request-approved' => ['requestModel' => $requestModel],
            'emails.request-rejected' => ['requestModel' => $requestModel, 'terminal' => false, 'comment' => ''],
            'emails.request-returned' => ['requestModel' => $requestModel, 'fromRole' => 'Reviewer', 'comment' => ''],
            'emails.voting-opened' => ['requestModel' => $requestModel],
            'emails.test-email' => [],
            'emails.system.mfa-otp' => ['user_name' => 'User', 'otp_code' => '123456', 'ttl_minutes' => 5],
            'emails.system.password-recovery-otp' => ['user_name' => 'User', 'otp_code' => '123456', 'ttl_minutes' => 5],
        ];
    }

    private function confirmationData(): array
    {
        return [
            'date' => '2026-06-07',
            'documentNumber' => 'DOC-1',
            'merchantName' => 'Importer Co.',
            'taxNumber' => 'TAX-1',
            'bankName' => 'Commercial Bank',
            'referenceNumber' => 'YFH-2026-000001',
            'goodsType' => 'Food',
            'currency' => 'USD',
            'amount' => 1000,
            'yerEquivalent' => 530000,
            'arrivalPort' => 'Aden',
            'quantity' => '10',
            'commercialRegNo' => 'CR-1',
            'committeeApprovalNo' => 'NC-1',
            'supplierName' => 'Supplier',
            'originCountry' => 'Country',
            'invoiceNumber' => 'INV-1',
            'invoiceDate' => '2026-06-01',
            'paymentTerms' => 'At sight',
            'dueDate' => '2026-07-01',
            'goodsDescription' => 'Goods',
            'shippingPort' => 'Port',
            'customsOffice' => 'Customs',
            'blNumber' => 'BL-1',
            'attachedDocs' => [
                ['label' => 'Invoice', 'attached' => true],
                ['label' => 'Register', 'attached' => false],
            ],
        ];
    }

    private function requestModel(): object
    {
        return (object) [
            'reference_number' => 'YFH-2026-000001',
            'bank' => (object) ['name' => 'Commercial Bank', 'code' => 'CB'],
            'supplier_name' => 'Supplier',
            'amount' => 1000,
            'currency' => 'USD',
            'goods_description' => 'Goods',
            'port_of_entry' => 'Aden',
            'bank_approved_at' => null,
            'support_approved_at' => null,
            'executive_decided_at' => null,
            'creator' => (object) ['name' => 'Requester'],
            'id' => 1,
        ];
    }
}
