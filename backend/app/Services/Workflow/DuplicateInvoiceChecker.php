<?php

namespace App\Services\Workflow;

use App\Models\EngineRequest;
use App\Services\Settings\SettingResolver;
use App\Support\InvoiceKey;

class DuplicateInvoiceChecker
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array{code: string, severity: string, message: string, duplicates: array<int, array{id: int, reference: string}>}|null
     */
    public function check(string $invoiceNumber, ?int $excludeRequestId = null): ?array
    {
        $normalized = InvoiceKey::normalize($invoiceNumber);

        if ($normalized === '') {
            return null;
        }

        $query = EngineRequest::query()
            ->where('invoice_number_normalized', $normalized)
            ->where('status', 'ACTIVE');

        if ($excludeRequestId !== null) {
            $query->where('id', '!=', $excludeRequestId);
        }

        $duplicates = $query->select(['id', 'reference', 'bank_id'])->get();

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
