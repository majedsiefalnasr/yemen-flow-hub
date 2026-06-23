<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngineRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'version' => $this->version,
            'workflow_version_id' => $this->workflow_version_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => [
                'id' => $this->currentStage->id,
                'code' => $this->currentStage->code,
                'name' => $this->currentStage->name,
                'is_initial' => $this->currentStage->is_initial,
                'is_final' => $this->currentStage->is_final,
                'sla_duration_minutes' => $this->currentStage->sla_duration_minutes,
            ]),
            'bank_id' => $this->bank_id,
            'bank' => $this->whenLoaded('bank', fn () => [
                'id' => $this->bank->id,
                'name' => $this->bank->name,
                'code' => $this->bank->code ?? null,
            ]),
            'merchant_id' => $this->merchant_id,
            'merchant' => $this->whenLoaded('merchant', fn () => [
                'id' => $this->merchant->id,
                'name' => $this->merchant->name,
            ]),
            'data' => $this->data,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'invoice_number' => $this->invoice_number,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
