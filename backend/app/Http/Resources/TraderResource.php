<?php

namespace App\Http\Resources;

use App\Models\Trader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lookup/autofill shape for 17-B.3 and 17-C:
 * trader fields plus companies[] and owners[] nested collections.
 */
class TraderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Owner identification PII is restricted to trader-management roles
        // (code-review 17-B decision #9).
        $canViewPii = (bool) $request->user()?->can('viewPii', Trader::class);

        return [
            'id' => $this->id,
            'tax_number' => $this->tax_number,
            'trader_name' => $this->trader_name,
            'tax_card_expiry' => $this->tax_card_expiry?->format('Y-m-d'),
            'commercial_registration_number' => $this->commercial_registration_number,
            'commercial_registration_expiry' => $this->commercial_registration_expiry?->format('Y-m-d'),
            'companies_count' => $this->whenCounted('companies'),
            'owners_count' => $this->whenCounted('owners'),
            'companies' => $this->whenLoaded('companies', fn () => $this->companies->map(fn ($company): array => [
                'id' => $company->id,
                'company_name' => $company->company_name,
            ])->values()->all(), []),
            'owners' => $this->whenLoaded('owners', fn () => $this->owners->map(fn ($owner): array => [
                'id' => $owner->id,
                'full_name' => $owner->full_name,
                'ownership_percentage' => (float) $owner->ownership_percentage,
                'nationality' => $canViewPii ? $owner->nationality : null,
                'identification_number' => $canViewPii ? $owner->identification_number : null,
            ])->values()->all(), []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
