<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'commercial_invoice', 'name_ar' => 'فاتورة تجارية', 'name_en' => 'Commercial Invoice', 'is_required' => true, 'sort_order' => 1],
            ['slug' => 'packing_list', 'name_ar' => 'قائمة التعبئة', 'name_en' => 'Packing List', 'is_required' => true, 'sort_order' => 2],
            ['slug' => 'bill_of_lading', 'name_ar' => 'سند الشحن', 'name_en' => 'Bill of Lading', 'is_required' => true, 'sort_order' => 3],
            ['slug' => 'certificate_of_origin', 'name_ar' => 'شهادة المنشأ', 'name_en' => 'Certificate of Origin', 'is_required' => true, 'sort_order' => 4],
            ['slug' => 'import_license', 'name_ar' => 'رخصة الاستيراد', 'name_en' => 'Import License', 'is_required' => true, 'sort_order' => 5],
            ['slug' => 'insurance_policy', 'name_ar' => 'بوليصة التأمين', 'name_en' => 'Insurance Policy', 'is_required' => false, 'sort_order' => 6],
            ['slug' => 'quality_certificate', 'name_ar' => 'شهادة الجودة', 'name_en' => 'Quality Certificate', 'is_required' => false, 'sort_order' => 7],
            ['slug' => 'other', 'name_ar' => 'مستند آخر', 'name_en' => 'Other', 'is_required' => false, 'sort_order' => 8],
        ];

        foreach ($rows as $row) {
            DocumentType::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'is_active' => true]
            );
        }
    }
}
