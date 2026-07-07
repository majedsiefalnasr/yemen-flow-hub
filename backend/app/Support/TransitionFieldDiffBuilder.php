<?php

namespace App\Support;

class TransitionFieldDiffBuilder
{
    public const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const STATIC_SENSITIVE_KEYS = ['amount', 'invoice_number'];

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $additionalSensitiveKeys
     * @return array{old_values: array<string, mixed>, new_values: array<string, mixed>}
     */
    public function diff(array $before, array $after, array $additionalSensitiveKeys = []): array
    {
        $sensitiveKeys = array_unique(array_merge(self::STATIC_SENSITIVE_KEYS, $additionalSensitiveKeys));
        $sensitiveSet = array_flip($sensitiveKeys);

        $oldValues = [];
        $newValues = [];

        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($allKeys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $isSensitive = isset($sensitiveSet[$key]);
            $oldValues[$key] = $isSensitive ? self::REDACTED : $old;
            $newValues[$key] = $isSensitive ? self::REDACTED : $new;
        }

        return [
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ];
    }
}
