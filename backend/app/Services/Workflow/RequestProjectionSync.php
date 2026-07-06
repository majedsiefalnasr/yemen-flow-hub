<?php

namespace App\Services\Workflow;

use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Support\InvoiceKey;

/**
 * Syncs Hybrid projection columns (DI-2) from JSON data to indexed columns on
 * engine_requests. Called on create, draft save, and transition.
 */
class RequestProjectionSync
{
    private const PROJECTION_MAP = [
        'amount' => 'amount',
        'currency' => 'currency',
        'invoice_number' => 'invoice_number',
        'request_percentage' => 'request_percentage',
    ];

    public function sync(EngineRequest $request): void
    {
        $data = $request->data ?? [];
        $fields = FieldDefinition::query()
            ->where('workflow_version_id', $request->workflow_version_id)
            ->pluck('key')
            ->all();

        $updates = [];
        foreach (self::PROJECTION_MAP as $fieldKey => $column) {
            if (in_array($fieldKey, $fields, true) && array_key_exists($fieldKey, $data)) {
                $updates[$column] = $this->normalize($column, $data[$fieldKey]);
            }
        }

        if (isset($updates['invoice_number'])) {
            $updates['invoice_number_normalized'] = InvoiceKey::normalize($updates['invoice_number'] ?? '');
        }

        if ($updates !== []) {
            $request->forceFill($updates)->saveQuietly();
        }
    }

    /**
     * Coerce a raw JSON value into a shape the typed/indexed column accepts. Returns
     * null for values that cannot be safely projected (non-numeric amount, non-scalar
     * string) so an invalid payload never aborts the surrounding transaction with a
     * QueryException or silently truncates.
     */
    private function normalize(string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($column) {
            'amount', 'request_percentage' => is_numeric($value) ? (string) $value : null,
            'currency' => is_scalar($value) ? mb_substr((string) $value, 0, 10) : null,
            'invoice_number' => is_scalar($value) ? mb_substr((string) $value, 0, 100) : null,
            default => $value,
        };
    }
}
