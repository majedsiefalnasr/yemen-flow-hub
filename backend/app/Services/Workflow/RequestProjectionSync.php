<?php

namespace App\Services\Workflow;

use App\Models\EngineRequest;
use App\Models\FieldDefinition;

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
                $updates[$column] = $data[$fieldKey];
            }
        }

        if ($updates !== []) {
            $request->forceFill($updates)->saveQuietly();
        }
    }
}
