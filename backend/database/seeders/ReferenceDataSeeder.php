<?php

namespace Database\Seeders;

use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            'sector_activity' => 'النشاط القطاعي',
            'arrival_port' => 'ميناء الوصول',
            'origin_country' => 'بلد المنشأ',
        ];

        $values = [
            'sector_activity' => [
                'food_beverages' => 'الأغذية والمشروبات',
                'medical_pharma' => 'الأدوية والمستلزمات الطبية',
                'construction_materials' => 'مواد البناء',
                'fuel_energy' => 'الوقود والطاقة',
            ],
            'arrival_port' => [
                'aden_port' => 'ميناء عدن',
                'hodeidah_port' => 'ميناء الحديدة',
                'mukalla_port' => 'ميناء المكلا',
            ],
            'origin_country' => [
                'cn' => 'الصين',
                'ae' => 'الإمارات العربية المتحدة',
                'in' => 'الهند',
                'tr' => 'تركيا',
            ],
        ];

        foreach ($tables as $tableKey => $tableLabel) {
            $referenceTable = ReferenceTable::query()->updateOrCreate(
                ['key' => $tableKey],
                ['label' => $tableLabel, 'is_system' => true, 'is_active' => true]
            );

            $sortOrder = 0;
            foreach ($values[$tableKey] as $valueKey => $valueLabel) {
                ReferenceValue::query()->updateOrCreate(
                    ['reference_table_id' => $referenceTable->id, 'key' => $valueKey],
                    ['label' => $valueLabel, 'is_system' => true, 'is_active' => true, 'sort_order' => $sortOrder++]
                );
            }
        }
    }
}
