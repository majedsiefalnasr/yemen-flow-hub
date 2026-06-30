<?php

namespace Database\Seeders;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowActionKind;
use App\Enums\WorkflowVersionState;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the canonical "تمويل الواردات" (Import Financing) workflow, mirroring
 * the dynamic-workflow-engine reference seed: one definition, one published
 * version, 8 stages, 12 transitions, 4 field groups, ~35 field definitions,
 * per-stage field rules, and stage permissions bound to the governance
 * org/team/role rows created by GovernanceSeeder.
 *
 * Idempotent: skipped entirely if the IMPORT_FINANCING definition exists.
 */
class ImportFinancingWorkflowSeeder extends Seeder
{
    private const DEFINITION_CODE = 'IMPORT_FINANCING';

    public function run(): void
    {
        $definition = WorkflowDefinition::query()->where('code', self::DEFINITION_CODE)->first();

        // A definition with at least one stage means the full seed already ran — skip.
        if ($definition && WorkflowStage::query()->where('workflow_version_id', $definition->versions()->first()?->id)->exists()) {
            $this->command?->info('Import Financing workflow already seeded — skipping.');

            return;
        }

        // Partial/orphan state (definition without stages) — wipe and reseed cleanly.
        if ($definition) {
            $definition->delete(); // cascades versions → stages → transitions → fields → rules → permissions
            $this->command?->info('Removed partial Import Financing workflow — reseeding.');
        }

        $this->ensureActions();

        $version = $this->seedDefinitionAndVersion();
        $groups = $this->seedFieldGroups($version->id);
        $fields = $this->seedFields($version->id, $groups);
        $stages = $this->seedStages($version->id);
        $this->seedTransitions($version->id, $stages);
        $this->seedFieldRules($stages, $fields);
        $this->seedStagePermissions($stages);

        $this->command?->info('Seeded Import Financing workflow definition + version + stages + transitions + fields + rules + permissions.');
    }

    /**
     * The reference uses REJECT_FINAL; ensure it is present alongside the
     * standard WorkflowActionSeeder catalog (which omits it).
     */
    private function ensureActions(): void
    {
        WorkflowAction::query()->firstOrCreate(
            ['code' => 'REJECT_FINAL'],
            [
                'name' => 'رفض نهائي',
                'kind' => WorkflowActionKind::REJECT,
                'is_active' => true,
                'is_system' => true,
            ],
        );
    }

    private function seedDefinitionAndVersion(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create([
            'code' => self::DEFINITION_CODE,
            'name' => 'تمويل الواردات',
            'description' => 'سير العمل الكامل لطلبات تمويل الواردات من إدخال البنك حتى الاعتماد النهائي.',
            'is_active' => true,
        ]);

        return WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
        ]);
    }

    /**
     * @return array<string, int> group key → id
     */
    private function seedFieldGroups(int $versionId): array
    {
        $rows = [
            ['basic', 'المعلومات الأساسية', 1],
            ['invoice', 'بيانات الفاتورة', 2],
            ['shipping', 'بيانات الشحن', 3],
            ['docs', 'الوثائق المطلوبة', 4],
        ];

        $ids = [];
        foreach ($rows as [$key, $label, $order]) {
            $group = FieldGroup::query()->create([
                'workflow_version_id' => $versionId,
                'name' => $key,
                'label' => $label,
                'sort_order' => $order,
            ]);
            $ids[$key] = $group->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $groups
     * @return array<string, int> field key → id
     */
    private function seedFields(int $versionId, array $groups): array
    {
        // [key, label, type, group, options|null, dynamic_source|null]
        $rows = [
            // basic
            ['taxNumber', 'الرقم الضريبي', FieldType::TEXT, 'basic', null, null],
            ['importerName', 'اسم التاجر', FieldType::DYNAMIC_SELECT, 'basic', null, 'MERCHANTS'],
            ['linkedCompany', 'الشركة المرتبطة', FieldType::DYNAMIC_SELECT, 'basic', null, 'MERCHANT_COMPANIES'],
            ['taxCardExpiry', 'تاريخ انتهاء البطاقة الضريبية', FieldType::DATE, 'basic', null, null],
            ['commercialRegistration', 'رقم السجل التجاري', FieldType::TEXT, 'basic', null, null],
            ['commercialRegistrationExpiry', 'تاريخ انتهاء السجل التجاري', FieldType::DATE, 'basic', null, null],
            ['owners', 'الملاك والمساهمون (25% فأكثر)', FieldType::TEXTAREA, 'basic', null, null],
            // invoice
            ['requestType', 'نوع الطلب', FieldType::SELECT, 'invoice', ['طلب مصارفة وتحويل خارجي', 'طلب تمويل واردات', 'طلب اعتماد مستندي'], null],
            ['coverageType', 'نوع التغطية', FieldType::SELECT, 'invoice', ['اعتماد مستندي', 'تحويل مباشر', 'دفعة مقدمة'], null],
            ['foreignCurrencySource', 'مصادر العملة الأجنبية', FieldType::SELECT, 'invoice', ['حساب العميل', 'موارد البنك', 'مصدر خارجي'], null],
            ['paymentTerms', 'شروط الدفع', FieldType::SELECT, 'invoice', ['كلي', 'جزئي'], null],
            ['requestCurrency', 'عملة الطلب', FieldType::SELECT, 'invoice', ['دولار أمريكي', 'يورو', 'ريال سعودي'], null],
            ['requestPercentage', 'نسبة الطلب %', FieldType::NUMBER, 'invoice', null, null],
            ['invoiceType', 'نوع الفاتورة', FieldType::SELECT, 'invoice', ['فاتورة تجارية', 'فاتورة أولية'], null],
            ['financeAmount', 'إجمالي الطلب', FieldType::CURRENCY, 'invoice', null, null],
            ['currency', 'عملة الفاتورة', FieldType::SELECT, 'invoice', ['دولار أمريكي', 'يورو', 'ريال سعودي'], null],
            ['invoiceNumber', 'رقم الفاتورة', FieldType::TEXT, 'invoice', null, null],
            ['invoiceDate', 'تاريخ الفاتورة', FieldType::DATE, 'invoice', null, null],
            ['quantity', 'الكمية', FieldType::NUMBER, 'invoice', null, null],
            ['unit', 'وحدة القياس', FieldType::TEXT, 'invoice', null, null],
            ['invoiceTotal', 'إجمالي الفاتورة', FieldType::CURRENCY, 'invoice', null, null],
            ['importType', 'السلعة', FieldType::DYNAMIC_SELECT, 'invoice', null, 'REFERENCE_DATA'],
            ['supplierName', 'اسم الشركة المصدرة', FieldType::TEXT, 'invoice', null, null],
            ['supplierLocation', 'موقع الشركة المصدرة', FieldType::TEXT, 'invoice', null, null],
            ['originCountry', 'بلد المنشأ', FieldType::DYNAMIC_SELECT, 'invoice', null, 'REFERENCE_DATA'],
            // shipping
            ['shippingDate', 'تاريخ الشحن', FieldType::DATE, 'shipping', null, null],
            ['arrivalDate', 'تاريخ الوصول', FieldType::DATE, 'shipping', null, null],
            ['shippingPort', 'ميناء الشحن', FieldType::TEXT, 'shipping', null, null],
            ['arrivalPort', 'ميناء الوصول', FieldType::DYNAMIC_SELECT, 'shipping', null, 'REFERENCE_DATA'],
            ['deliveryTerms', 'شروط التسليم', FieldType::SELECT, 'shipping', ['FOB', 'CIF', 'CFR'], null],
            ['finalDestination', 'الوجهة النهائية', FieldType::TEXT, 'shipping', null, null],
            // docs
            ['docYemeniRialSharia', 'كشف حساب بالريال اليمني (مناطق الشرعية)', FieldType::FILE, 'docs', null, null],
            ['docSaudiRialSharia', 'كشف حساب بالريال السعودي (مناطق الشرعية)', FieldType::FILE, 'docs', null, null],
            ['docUsdSharia', 'كشف حساب بالدولار الأمريكي (مناطق الشرعية)', FieldType::FILE, 'docs', null, null],
            ['docTaxAndCr', 'البطاقة الضريبية والسجل التجاري', FieldType::FILE, 'docs', null, null],
            ['docCommercialInvoice', 'الفاتورة', FieldType::FILE, 'docs', null, null],
            ['docLicenses', 'التراخيص المطلوبة لبعض السلع', FieldType::FILE, 'docs', null, null],
            ['docExtra', 'مستندات إضافية', FieldType::FILE, 'docs', null, null],
        ];

        $ids = [];
        foreach ($rows as $index => [$key, $label, $type, $groupKey, $options, $source]) {
            $field = FieldDefinition::query()->create([
                'workflow_version_id' => $versionId,
                'field_group_id' => $groups[$groupKey],
                'key' => $key,
                'label' => $label,
                'type' => $type->value,
                'options' => $options,
                'dynamic_source' => $source,
                'is_system' => true,
                'sort_order' => $index + 1,
            ]);
            $ids[$key] = $field->id;
        }

        return $ids;
    }

    /**
     * @return array<string, int> stage code → id
     */
    private function seedStages(int $versionId): array
    {
        $rows = [
            ['CREATE', 'إنشاء الطلب', 1, true, false],
            ['INTERNAL', 'المراجعة الداخلية', 2, false, false],
            ['SUPPORT', 'المراجعة المساندة', 3, false, false],
            ['EXEC', 'القرار التنفيذي', 4, false, false],
            ['FX', 'عمليات الصرف', 5, false, false],
            ['FX_CONFIRM', 'تأكيد الصرف', 6, false, false],
            ['FINAL', 'الاعتماد النهائي', 7, false, false],
            ['CLOSED', 'مغلق', 99, false, true],
        ];

        $ids = [];
        foreach ($rows as [$code, $name, $order, $isInitial, $isFinal]) {
            $stage = WorkflowStage::query()->create([
                'workflow_version_id' => $versionId,
                'code' => $code,
                'name' => $name,
                'sort_order' => $order,
                'is_initial' => $isInitial,
                'is_final' => $isFinal,
            ]);
            $ids[$code] = $stage->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $stages
     */
    private function seedTransitions(int $versionId, array $stages): void
    {
        $actionId = fn (string $code) => WorkflowAction::query()
            ->where('code', $code)
            ->value('id');

        // [from, to, actionCode]
        $rows = [
            ['CREATE', 'INTERNAL', 'APPROVE'],
            ['INTERNAL', 'SUPPORT', 'APPROVE'],
            ['INTERNAL', 'CREATE', 'REJECT'],
            ['SUPPORT', 'EXEC', 'APPROVE'],
            ['SUPPORT', 'SUPPORT', 'ADD_NOTES'],
            ['EXEC', 'FX', 'APPROVE'],
            ['EXEC', 'CLOSED', 'REJECT_FINAL'],
            ['FX', 'FX_CONFIRM', 'APPROVE'],
            ['FX_CONFIRM', 'FINAL', 'APPROVE'],
            ['FX_CONFIRM', 'FX', 'REJECT'],
            ['FINAL', 'CLOSED', 'FINAL_APPROVE'],
            ['FINAL', 'FX_CONFIRM', 'REJECT'],
        ];

        foreach ($rows as [$from, $to, $actionCode]) {
            WorkflowTransition::query()->create([
                'workflow_version_id' => $versionId,
                'from_stage_id' => $stages[$from],
                'action_id' => $actionId($actionCode),
                'to_stage_id' => $stages[$to],
            ]);
        }
    }

    /**
     * @param  array<string, int>  $stages
     * @param  array<string, int>  $fields
     */
    private function seedFieldRules(array $stages, array $fields): void
    {
        $requiredOnCreate = [
            'taxNumber', 'importerName', 'linkedCompany', 'taxCardExpiry', 'commercialRegistration', 'commercialRegistrationExpiry',
            'requestType', 'coverageType', 'foreignCurrencySource', 'paymentTerms', 'requestCurrency', 'requestPercentage',
            'invoiceType', 'financeAmount', 'currency', 'invoiceNumber', 'invoiceDate', 'quantity', 'unit', 'invoiceTotal',
            'importType', 'supplierName', 'supplierLocation', 'originCountry',
            'shippingDate', 'arrivalDate', 'shippingPort', 'arrivalPort', 'deliveryTerms', 'finalDestination',
            'docYemeniRialSharia', 'docSaudiRialSharia', 'docUsdSharia', 'docTaxAndCr', 'docCommercialInvoice',
        ];

        $now = now();
        $rows = [];

        // CREATE — all fields visible + editable; subset required.
        foreach ($fields as $key => $fieldId) {
            $rows[] = [
                'stage_id' => $stages['CREATE'],
                'field_id' => $fieldId,
                'is_visible' => true,
                'is_editable' => true,
                'is_required' => in_array($key, $requiredOnCreate, true),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // All downstream stages — submitted data visible + read-only.
        foreach (['INTERNAL', 'SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL', 'CLOSED'] as $stageCode) {
            foreach ($fields as $fieldId) {
                $rows[] = [
                    'stage_id' => $stages[$stageCode],
                    'field_id' => $fieldId,
                    'is_visible' => true,
                    'is_editable' => false,
                    'is_required' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // chunk insert to avoid huge single statement
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('stage_field_rules')->insert($chunk);
        }
    }

    /**
     * @param  array<string, int>  $stages
     */
    private function seedStagePermissions(array $stages): void
    {
        $resolveOrg = fn (string $code) => Organization::query()->where('code', $code)->value('id');
        $resolveTeam = function (string $orgCode, string $teamCode) {
            $org = Organization::query()->where('code', $orgCode)->value('id');

            return Team::query()->where('organization_id', $org)->where('code', $teamCode)->value('id');
        };
        $resolveRole = function (string $orgCode, string $roleCode) {
            $org = Organization::query()->where('code', $orgCode)->value('id');

            return Role::query()->where('organization_id', $org)->where('code', $roleCode)->value('id');
        };

        // [stageCode, orgCode, teamCode|null, roleCode|null, accessLevel, displayLabel]
        $rows = [
            ['CREATE', 'commercial_banks', 'entry', null, StageAccessLevel::EXECUTE, 'تقديم الطلب'],
            ['INTERNAL', 'commercial_banks', 'internal_review', null, StageAccessLevel::EXECUTE, 'المراجعة الداخلية بالبنك'],
            ['SUPPORT', 'national_committee', 'support', null, StageAccessLevel::EXECUTE, 'مراجعة اللجنة المساندة'],
            ['EXEC', 'national_committee', 'executive', 'committee_manager', StageAccessLevel::EXECUTE, 'قرار اللجنة التنفيذية'],
            ['FX', 'commercial_banks', 'fx_ops', null, StageAccessLevel::EXECUTE, 'تنفيذ عملية الصرف'],
            ['FX_CONFIRM', 'national_committee', 'fx_confirmation', null, StageAccessLevel::EXECUTE, 'تأكيد عملية الصرف'],
            ['FINAL', 'national_committee', 'executive', 'committee_manager', StageAccessLevel::EXECUTE, 'الاعتماد النهائي'],
            ['CLOSED', 'national_committee', 'executive', 'committee_manager', StageAccessLevel::VIEW, 'إغلاق الطلب'],
        ];

        foreach ($rows as [$stageCode, $orgCode, $teamCode, $roleCode, $level, $label]) {
            StagePermission::query()->create([
                'stage_id' => $stages[$stageCode],
                'organization_id' => $resolveOrg($orgCode),
                'team_id' => $teamCode ? $resolveTeam($orgCode, $teamCode) : null,
                'role_id' => $roleCode ? $resolveRole($orgCode, $roleCode) : null,
                'user_id' => null,
                'access_level' => $level->value,
                'display_label' => $label,
            ]);
        }
    }
}
