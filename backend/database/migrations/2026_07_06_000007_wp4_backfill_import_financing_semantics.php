<?php

use App\Enums\FieldSemanticTag;
use App\Enums\StageSemanticRole;
use App\Enums\WorkflowEffectCode;
use App\Models\FieldDefinition;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->first();
        if ($definition === null) {
            return;
        }

        $versionIds = $definition->versions()->pluck('id');
        if ($versionIds->isEmpty()) {
            return;
        }

        $fieldTags = [
            'invoice_number' => FieldSemanticTag::INVOICE_NUMBER->value,
            'request_percentage' => FieldSemanticTag::REQUESTED_PERCENTAGE->value,
            'taxNumber' => FieldSemanticTag::MERCHANT_TAX_NUMBER->value,
            'supplierName' => FieldSemanticTag::SUPPLIER_NAME->value,
            'importType' => FieldSemanticTag::GOODS_DESCRIPTION->value,
            'arrivalPort' => FieldSemanticTag::PORT_OF_ENTRY->value,
            'amount' => FieldSemanticTag::AMOUNT->value,
            'currency' => FieldSemanticTag::CURRENCY->value,
        ];

        foreach ($fieldTags as $key => $tag) {
            FieldDefinition::query()
                ->whereIn('workflow_version_id', $versionIds)
                ->where('key', $key)
                ->update(['semantic_tag' => $tag]);
        }

        $stageRoles = [
            'CREATE' => StageSemanticRole::INITIAL_ENTRY->value,
            'INTERNAL' => StageSemanticRole::BANK_REVIEW->value,
            'SUPPORT' => StageSemanticRole::SUPPORT_REVIEW->value,
            'EXEC' => StageSemanticRole::EXECUTIVE_VOTE->value,
            'FX' => StageSemanticRole::SWIFT->value,
            'FX_CONFIRM' => StageSemanticRole::FX_CONFIRMATION->value,
            'FINAL' => StageSemanticRole::FINAL->value,
        ];

        foreach ($stageRoles as $code => $role) {
            WorkflowStage::query()
                ->whereIn('workflow_version_id', $versionIds)
                ->where('code', $code)
                ->update(['semantic_role' => $role]);
        }

        WorkflowStage::query()
            ->whereIn('workflow_version_id', $versionIds)
            ->where('code', 'EXEC')
            ->update(['attached_effects' => json_encode([WorkflowEffectCode::FINANCING_RESERVE->value])]);

        WorkflowStage::query()
            ->whereIn('workflow_version_id', $versionIds)
            ->where('code', 'FX_CONFIRM')
            ->update(['attached_effects' => json_encode([WorkflowEffectCode::FX_CONFIRMATION_PDF->value])]);
    }

    public function down(): void
    {
        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->first();
        if ($definition === null) {
            return;
        }

        $versionIds = $definition->versions()->pluck('id');
        if ($versionIds->isEmpty()) {
            return;
        }

        DB::table('field_definitions')->whereIn('workflow_version_id', $versionIds)->update(['semantic_tag' => null]);
        DB::table('workflow_stages')->whereIn('workflow_version_id', $versionIds)->update([
            'semantic_role' => null,
            'attached_effects' => null,
        ]);
    }
};
