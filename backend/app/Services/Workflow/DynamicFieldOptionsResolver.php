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
 */
class DynamicFieldOptionsResolver
{
    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public function resolve(FieldDefinition $field, User $user, ?EngineRequest $request = null): array
    {
        $scope = DataScope::forUser($user);

        // NC viewer on a specific request sees that request's bank's merchants (WP-8 F-1).
        if ($request && $scope->systemWide) {
            $scope = new DataScopeContext(systemWide: false, ownBankId: $request->bank_id);
        }

        return match ($field->dynamic_source) {
            DynamicFieldSource::MERCHANTS => $this->fromQuery(
                DataScope::applyTo(Merchant::query(), $scope)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'id',
                'name',
            ),
            DynamicFieldSource::MERCHANT_COMPANIES => $this->fromQuery(
                MerchantCompany::query()
                    ->whereHas('merchant', fn ($q) => DataScope::applyTo($q, $scope))
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'id',
                'name',
            ),
            DynamicFieldSource::REFERENCE_DATA => $this->fromReferenceTable($field),
            default => [],
        };
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
