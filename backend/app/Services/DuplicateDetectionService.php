<?php

namespace App\Services;

use App\Exceptions\DuplicateInvoiceMismatchException;
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

    /**
     * @param  array{
     *     tax_number?: string|null,
     *     trader_snapshot_tax_number?: string|null,
     *     invoice_number?: string|null,
     *     invoice_currency?: string|null,
     *     total_invoice_amount?: float|int|string|null
     * }  $candidate
     */
    public function assertInvoiceKeyConsistency(array $candidate, ?int $excludeRequestId = null): void
    {
        $taxNumber = $candidate['trader_snapshot_tax_number'] ?? $candidate['tax_number'] ?? null;
        $invoiceNumber = $candidate['invoice_number'] ?? null;

        if ($taxNumber === null || $invoiceNumber === null) {
            return;
        }

        $existingRows = ImportRequest::query()
            ->where('trader_snapshot_tax_number', $taxNumber)
            ->where('invoice_number', $invoiceNumber)
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->get([
                'trader_snapshot_tax_number',
                'invoice_number',
                'invoice_currency',
                'total_invoice_amount',
            ]);

        if ($existingRows->isEmpty()) {
            return;
        }

        $reference = $existingRows->first();

        $this->assertFieldMatches(
            'tax_number',
            (string) $taxNumber,
            (string) $reference->trader_snapshot_tax_number,
        );
        $this->assertFieldMatches(
            'invoice_number',
            (string) $invoiceNumber,
            (string) $reference->invoice_number,
        );
        $this->assertFieldMatches(
            'invoice_currency',
            $this->normalizeNullableString($candidate['invoice_currency'] ?? null),
            $this->normalizeNullableString($reference->invoice_currency),
        );
        $this->assertFieldMatches(
            'total_invoice_amount',
            $this->normalizeAmount($candidate['total_invoice_amount'] ?? null),
            $this->normalizeAmount($reference->total_invoice_amount),
        );
    }

    private function assertFieldMatches(string $field, ?string $candidateValue, ?string $referenceValue): void
    {
        if ($candidateValue === null || $referenceValue === null) {
            return;
        }

        if ($candidateValue !== $referenceValue) {
            throw new DuplicateInvoiceMismatchException(
                message: 'Invoice key fields do not match existing requests for this tax number and invoice number.',
                mismatchedField: $field,
            );
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function normalizeAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
