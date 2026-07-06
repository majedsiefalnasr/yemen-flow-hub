<?php

namespace App\Services\Workflow;

use App\Models\EngineRequest;

class DuplicateInvoiceChecker
{
    /**
     * @return array{code: string, message: string, duplicates: array<int, array{id: int, reference: string}>}|null
     */
    public function check(string $invoiceNumber, ?int $excludeRequestId = null): ?array
    {
        // TODO(WP-7/R5s2): switch duplicate matching to InvoiceKey normalization with masking/backfill.
        if (trim($invoiceNumber) === '') {
            return null;
        }

        $query = EngineRequest::query()
            ->where('invoice_number', $invoiceNumber)
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
            'message' => 'Invoice number matches existing active request(s).',
            'duplicates' => $duplicates->map(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'bank_id' => $r->bank_id,
            ])->all(),
        ];
    }
}
