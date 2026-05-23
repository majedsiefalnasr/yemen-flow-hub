<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Seeder;

class MerchantSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['ar' => 'شركة الهدى للتجارة', 'en' => 'Al-Hadi Trading LLC'],
            ['ar' => 'مؤسسة النور للاستيراد', 'en' => 'Al-Noor Import Est.'],
            ['ar' => 'شركة اليمن الذهبية', 'en' => 'Golden Yemen Co.'],
            ['ar' => 'مجموعة الفلاح التجارية', 'en' => 'Al-Falah Commercial Group'],
            ['ar' => 'شركة الشرق للأدوية', 'en' => 'East Pharma Co.'],
            ['ar' => 'مؤسسة الجزيرة للأغذية', 'en' => 'Al-Jazeera Foods Est.'],
            ['ar' => 'شركة السلام للإلكترونيات', 'en' => 'Al-Salam Electronics'],
            ['ar' => 'مجموعة الأمل للأقمشة', 'en' => 'Al-Amal Textiles Group'],
            ['ar' => 'شركة المختار للمعدات', 'en' => 'Al-Mukhtar Equipment Co.'],
            ['ar' => 'مؤسسة عدن البحرية', 'en' => 'Aden Maritime Est.'],
            ['ar' => 'شركة صنعاء للمواد الغذائية', 'en' => "Sana'a Food Materials"],
            ['ar' => 'مجموعة حضرموت التجارية', 'en' => 'Hadramaut Trading Group'],
            ['ar' => 'شركة تعز للأدوات الصحية', 'en' => 'Taiz Sanitary Co.'],
            ['ar' => 'مؤسسة الحديدة للنقل', 'en' => 'Hodeidah Transport Est.'],
            ['ar' => 'شركة المكلا للحبوب', 'en' => 'Mukalla Grains Co.'],
        ];

        $businessTypes = ['Trading', 'Manufacturing', 'Wholesale', 'Pharma', 'Food', 'Electronics', 'Textiles', 'Logistics'];

        Bank::query()->where('is_active', true)->orderBy('id')->get()->each(function (Bank $bank) use ($templates, $businessTypes): void {
            $manager = User::query()
                ->where('bank_id', $bank->id)
                ->where('role', 'DATA_ENTRY')
                ->first();

            foreach ($templates as $i => $template) {
                // ~10% inactive across the merchant population
                $isActive = (($bank->id + $i) % 10) !== 0;

                Merchant::query()->updateOrCreate(
                    [
                        'bank_id' => $bank->id,
                        'name' => "{$template['ar']} / {$template['en']}",
                    ],
                    [
                        'commercial_register' => 'CR-2024-'.random_int(100000, 999999),
                        'tax_number' => 'TAX-'.random_int(10000000, 99999999),
                        'national_id' => (string) random_int(1000000000, 9999999999),
                        'owner_name' => fake()->name(),
                        'phone' => '+967 7'.random_int(0, 9).' '.random_int(100, 999).' '.random_int(1000, 9999),
                        'email' => strtolower($bank->code).'-m'.($i + 1).'@merchant.ye',
                        'address' => 'Yemen, Sana\'a',
                        'business_type' => $businessTypes[$i % count($businessTypes)],
                        'is_active' => $isActive,
                        'created_by' => $manager?->id,
                    ]
                );
            }
        });
    }
}
