<?php

namespace App\Http\Resources;

use App\Enums\StageAccessLevel;
use App\Services\Workflow\StagePermissionResolver;
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
                'requires_claim' => $this->currentStage->requires_claim,
            ]),
            // Whether the signed-in user may EXECUTE the current stage. Drives the
            // detail page's action panel and edit mode. Assignment-based even for
            // system admins (admins widen visibility, never execute authority),
            // matching the workflow-designer routing. Absent when the stage is not
            // loaded (list endpoints), where the client does not need it.
            'can_execute' => $this->when(
                $request->user() !== null && $this->relationLoaded('currentStage') && $this->currentStage !== null,
                fn (): bool => app(StagePermissionResolver::class)->userCanAccessStage(
                    $request->user(),
                    $this->currentStage,
                    StageAccessLevel::EXECUTE,
                ),
            ),
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
            'sla_status' => $this->sla_status,
            'claimed_by' => $this->claimed_by,
            'claimed_by_user' => $this->whenLoaded('claimedBy', fn () => $this->claimedBy === null ? null : [
                'id' => $this->claimedBy->id,
                'name' => $this->claimedBy->name,
            ]),
            'claimed_at' => $this->claimed_at?->toISOString(),
            'claim_expires_at' => $this->claim_expires_at?->toISOString(),
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
