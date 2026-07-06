<?php

namespace App\Services\Workflow;

use App\DTOs\Authorization\DataScopeContext;
use App\Enums\DynamicFieldSource;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\ReferenceValue;
use App\Models\User;
use App\Services\Authorization\DataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the selectable options for a DYNAMIC_SELECT field (DI-5). Options are
 * returned as {value, label} where value is a stable id/key (stored in
 * requests.data, never the label — FR-REF2).
 *
 * Active options are returned for new selection. Stored values that are no longer
 * active are appended with `inactive: true` so existing requests stay readable
 * (WP-8 F-3 grandfathering).
 */
class DynamicFieldOptionsResolver
{
    /**
     * @return array<int, array{value: int|string, label: string, inactive?: bool}>
     */
    public function resolve(
        FieldDefinition $field,
        User $user,
        ?EngineRequest $request = null,
        mixed $storedValue = null,
    ): array {
        $scope = DataScope::forUser($user);

        // NC viewer on a specific request sees that request's bank's merchants (WP-8 F-1).
        if ($request && $scope->systemWide) {
            $scope = new DataScopeContext(systemWide: false, ownBankId: $request->bank_id);
        }

        $activeOptions = match ($field->dynamic_source) {
            DynamicFieldSource::MERCHANTS => $this->fromQuery(
                DataScope::applyTo(Merchant::query()->active(), $scope)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'id',
                'name',
            ),
            DynamicFieldSource::MERCHANT_COMPANIES => $this->fromQuery(
                MerchantCompany::query()
                    ->where('is_active', true)
                    ->whereHas('merchant', fn ($q) => DataScope::applyTo($q, $scope))
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'id',
                'name',
            ),
            DynamicFieldSource::REFERENCE_DATA => $this->fromReferenceTable($field),
            default => [],
        };

        return $this->appendGrandfatheredOptions($field, $activeOptions, $this->storedValuesList($storedValue), $scope);
    }

    /**
     * @return list<mixed>
     */
    private function storedValuesList(mixed $storedValue): array
    {
        if ($storedValue === null || $storedValue === '') {
            return [];
        }

        if (is_array($storedValue)) {
            return array_values($storedValue);
        }

        return [$storedValue];
    }

    /**
     * @param  array<int, array{value: int|string, label: string}>  $activeOptions
     * @param  list<mixed>  $storedValues
     * @return array<int, array{value: int|string, label: string, inactive?: bool}>
     */
    private function appendGrandfatheredOptions(
        FieldDefinition $field,
        array $activeOptions,
        array $storedValues,
        DataScopeContext $scope,
    ): array {
        if ($storedValues === []) {
            return $activeOptions;
        }

        $activeKeys = collect($activeOptions)
            ->map(fn (array $option): string => (string) $option['value'])
            ->all();

        $grandfathered = [];
        foreach ($storedValues as $stored) {
            if ($stored === null || $stored === '') {
                continue;
            }

            if (in_array((string) $stored, $activeKeys, true)) {
                continue;
            }

            $option = $this->lookupInactiveOption($field, $stored, $scope);
            if ($option !== null) {
                $grandfathered[] = $option;
            }
        }

        return array_merge($activeOptions, $grandfathered);
    }

    /**
     * @return array{value: int|string, label: string, inactive: true}|null
     */
    private function lookupInactiveOption(
        FieldDefinition $field,
        mixed $stored,
        DataScopeContext $scope,
    ): ?array {
        return match ($field->dynamic_source) {
            DynamicFieldSource::MERCHANTS => $this->lookupInactiveMerchant($stored, $scope),
            DynamicFieldSource::MERCHANT_COMPANIES => $this->lookupInactiveMerchantCompany($stored, $scope),
            DynamicFieldSource::REFERENCE_DATA => $this->lookupInactiveReferenceValue($field, $stored),
            default => null,
        };
    }

    /**
     * @return array{value: int|string, label: string, inactive: true}|null
     */
    private function lookupInactiveMerchant(mixed $stored, DataScopeContext $scope): ?array
    {
        $merchant = DataScope::applyTo(Merchant::query(), $scope)
            ->where('id', $stored)
            ->first(['id', 'name']);

        if ($merchant === null) {
            return null;
        }

        return [
            'value' => $merchant->id,
            'label' => (string) $merchant->name,
            'inactive' => true,
        ];
    }

    /**
     * @return array{value: int|string, label: string, inactive: true}|null
     */
    private function lookupInactiveMerchantCompany(mixed $stored, DataScopeContext $scope): ?array
    {
        $company = MerchantCompany::query()
            ->where('id', $stored)
            ->whereHas('merchant', fn ($q) => DataScope::applyTo($q, $scope))
            ->first(['id', 'name']);

        if ($company === null) {
            return null;
        }

        return [
            'value' => $company->id,
            'label' => (string) $company->name,
            'inactive' => true,
        ];
    }

    /**
     * @return array{value: int|string, label: string, inactive: true}|null
     */
    private function lookupInactiveReferenceValue(FieldDefinition $field, mixed $stored): ?array
    {
        if ($field->reference_table_id === null) {
            return null;
        }

        $value = ReferenceValue::query()
            ->where('reference_table_id', $field->reference_table_id)
            ->where('key', (string) $stored)
            ->where('is_active', false)
            ->first(['key', 'label']);

        if ($value === null) {
            return null;
        }

        return [
            'value' => $value->key,
            'label' => $value->label,
            'inactive' => true,
        ];
    }

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    private function fromReferenceTable(FieldDefinition $field): array
    {
        if ($field->reference_table_id === null) {
            return [];
        }

        $values = ReferenceValue::query()
            ->where('reference_table_id', $field->reference_table_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'label']);

        return $values->map(fn ($value): array => [
            'value' => $value->key,
            'label' => $value->label,
        ])->all();
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return array<int, array{value: int|string, label: string}>
     */
    private function fromQuery(Collection $rows, string $valueColumn, string $labelColumn): array
    {
        return $rows->map(fn ($row): array => [
            'value' => $row->{$valueColumn},
            'label' => (string) $row->{$labelColumn},
        ])->all();
    }
}
