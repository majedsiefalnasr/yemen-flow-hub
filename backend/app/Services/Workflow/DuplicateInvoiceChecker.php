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
        if (trim($invoiceNumber) === '') {
            return null;
        }

        $query = EngineRequest::query()
            ->where('invoice_number', $invoiceNumber)
            ->where('status', 'ACTIVE');

        if ($excludeRequestId !== null) {
            $query->where('id', '!=', $excludeRequestId);
        }

        $duplicates = $query->select(['id', 'reference'])->get();

        if ($duplicates->isEmpty()) {
            return null;
        }

        return [
            'code' => 'DUPLICATE_INVOICE',
            'message' => 'Invoice number matches existing active request(s).',
            'duplicates' => $duplicates->map(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference,
            ])->all(),
        ];
    }
}
