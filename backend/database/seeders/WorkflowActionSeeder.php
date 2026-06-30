<?php

namespace Database\Seeders;

use App\Enums\WorkflowActionKind;
use App\Models\WorkflowAction;
use Illuminate\Database\Seeder;

class WorkflowActionSeeder extends Seeder
{
    public function run(): void
    {
        // Default reusable actions. Names are designer-editable; code/kind are the
        // stable contract referenced by transitions. (FR-WD3, prd #8 seed)
        $actions = [
            ['code' => 'SAVE_DRAFT', 'name' => 'حفظ كمسودة', 'kind' => WorkflowActionKind::DRAFT],
            ['code' => 'APPROVE', 'name' => 'اعتماد', 'kind' => WorkflowActionKind::APPROVE],
            ['code' => 'REJECT', 'name' => 'رفض', 'kind' => WorkflowActionKind::REJECT],
            ['code' => 'RETURN', 'name' => 'إعادة', 'kind' => WorkflowActionKind::RETURN],
            ['code' => 'CLOSE', 'name' => 'إغلاق', 'kind' => WorkflowActionKind::CLOSE],
            ['code' => 'MORE_INFO', 'name' => 'طلب معلومات إضافية', 'kind' => WorkflowActionKind::INFO],
            ['code' => 'ADD_NOTES', 'name' => 'إضافة ملاحظات', 'kind' => WorkflowActionKind::INFO],
            ['code' => 'UPLOAD_DOCS', 'name' => 'رفع مستندات', 'kind' => WorkflowActionKind::CUSTOM],
            ['code' => 'FINAL_APPROVE', 'name' => 'الاعتماد النهائي', 'kind' => WorkflowActionKind::APPROVE],
        ];

        foreach ($actions as $action) {
            WorkflowAction::query()->firstOrCreate(
                ['code' => $action['code']],
                [
                    'name' => $action['name'],
                    'kind' => $action['kind'],
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
