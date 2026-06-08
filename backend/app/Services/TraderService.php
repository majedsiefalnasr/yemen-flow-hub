<?php

namespace App\Services;

use App\Models\Trader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TraderService
{
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($filters['tax_number'] ?? null, fn (Builder $query, string $taxNumber) => $query->where('tax_number', 'like', "%{$taxNumber}%"))
            ->when($filters['trader_name'] ?? null, fn (Builder $query, string $traderName) => $query->where('trader_name', 'like', "%{$traderName}%"))
            ->latest('id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function find(int $id): Trader
    {
        return $this->baseQuery()->findOrFail($id);
    }

    public function loadDetails(Trader $trader): Trader
    {
        return $trader->load(['companies', 'owners'])->loadCount(['companies', 'owners']);
    }

    public function findByTaxNumber(string $taxNumber): ?Trader
    {
        return $this->baseQuery()
            ->where('tax_number', $taxNumber)
            ->first();
    }

    public function create(array $data): Trader
    {
        return DB::transaction(function () use ($data): Trader {
            $trader = Trader::query()->create($this->traderAttributes($data));

            $this->syncRelation($trader, 'companies', $data['companies'] ?? [], ['company_name']);
            $this->syncRelation($trader, 'owners', $data['owners'] ?? [], [
                'full_name',
                'ownership_percentage',
                'nationality',
                'identification_number',
            ]);

            return $this->refreshWithRelations($trader);
        });
    }

    public function update(Trader $trader, array $data): Trader
    {
        return DB::transaction(function () use ($trader, $data): Trader {
            $trader->update($this->traderAttributes($data, partial: true));

            if (array_key_exists('companies', $data)) {
                $this->syncRelation($trader, 'companies', $data['companies'] ?? [], ['company_name']);
            }

            if (array_key_exists('owners', $data)) {
                $this->syncRelation($trader, 'owners', $data['owners'] ?? [], [
                    'full_name',
                    'ownership_percentage',
                    'nationality',
                    'identification_number',
                ]);
            }

            return $this->refreshWithRelations($trader);
        });
    }

    public function buildSnapshot(Trader $trader): array
    {
        return [
            'trader_name' => $trader->trader_name,
            'tax_number' => $trader->tax_number,
            'commercial_registration_number' => $trader->commercial_registration_number,
            'commercial_registration_expiry' => $trader->commercial_registration_expiry?->format('Y-m-d'),
            'tax_card_expiry' => $trader->tax_card_expiry?->format('Y-m-d'),
        ];
    }

    private function traderAttributes(array $data, bool $partial = false): array
    {
        $attributes = Arr::only($data, [
            'tax_number',
            'trader_name',
            'tax_card_expiry',
            'commercial_registration_number',
            'commercial_registration_expiry',
        ]);

        return $partial ? array_filter($attributes, fn ($value): bool => $value !== null) : $attributes;
    }

    private function baseQuery(): Builder
    {
        return Trader::query()
            ->with(['companies', 'owners'])
            ->withCount(['companies', 'owners']);
    }

    private function refreshWithRelations(Trader $trader): Trader
    {
        return $trader->refresh()->load(['companies', 'owners'])->loadCount(['companies', 'owners']);
    }

    private function syncRelation(Trader $trader, string $relation, array $items, array $fields): void
    {
        $incomingIds = collect($items)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();
        $deleteQuery = $trader->{$relation}();

        if ($incomingIds !== []) {
            $deleteQuery->whereNotIn('id', $incomingIds);
        }

        $deleteQuery->delete();

        foreach ($items as $item) {
            $attributes = Arr::only($item, $fields);

            if (! empty($item['id'])) {
                $trader->{$relation}()->whereKey($item['id'])->update($attributes);

                continue;
            }

            $trader->{$relation}()->create($attributes);
        }
    }
}
