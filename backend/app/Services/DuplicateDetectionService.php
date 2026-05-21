<?php

namespace App\Services;

use App\Models\ImportRequest;
use Illuminate\Support\Collection;

class DuplicateDetectionService
{
    /**
     * Find all non-deleted requests sharing the given invoice_number,
     * optionally excluding the request being created/updated.
     */
    public function findDuplicatesForInvoice(string $invoiceNumber, ?int $excludeRequestId = null): Collection
    {
        return ImportRequest::query()
            ->with('bank:id,name')
            ->where('invoice_number', $invoiceNumber)
            ->when($excludeRequestId !== null, fn ($q) => $q->where('id', '!=', $excludeRequestId))
            ->select(['id', 'reference_number', 'bank_id', 'amount', 'currency', 'created_at', 'status'])
            ->get();
    }

    /**
     * Return all invoice numbers that appear more than once (cross-bank),
     * grouped by invoice_number. Each group is a Collection of ImportRequest rows.
     */
    public function findDuplicateGroups(): Collection
    {
        $duplicateInvoiceNumbers = ImportRequest::query()
            ->whereNotNull('invoice_number')
            ->select('invoice_number')
            ->groupBy('invoice_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('invoice_number');

        if ($duplicateInvoiceNumbers->isEmpty()) {
            return collect();
        }

        return ImportRequest::query()
            ->with('bank:id,name')
            ->whereIn('invoice_number', $duplicateInvoiceNumbers)
            ->select(['id', 'reference_number', 'bank_id', 'invoice_number', 'amount', 'currency', 'created_at', 'status'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('invoice_number');
    }
}
