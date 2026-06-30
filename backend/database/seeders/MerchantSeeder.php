<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\MerchantOwner;
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

        Bank::query()->where('is_active', true)->orderBy('id')->get()->each(function (Bank $bank) use ($templates): void {
            $manager = User::query()
                ->where('bank_id', $bank->id)
                ->where('role', 'DATA_ENTRY')
                ->first();

            foreach ($templates as $i => $template) {
                // ~10% inactive across the merchant population
                $status = (($bank->id + $i) % 10) === 0 ? 'INACTIVE' : 'ACTIVE';
                $name = "{$template['ar']} / {$template['en']}";

                $merchant = Merchant::query()->updateOrCreate(
                    [
                        'bank_id' => $bank->id,
                        'name' => $name,
                    ],
                    [
                        'tax_number' => 'TAX-'.$bank->id.'-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                        'tax_card_expiry' => now()->addMonths(random_int(3, 36))->toDateString(),
                        'phone' => '+967 7'.random_int(0, 9).' '.random_int(100, 999).' '.random_int(1000, 9999),
                        'address' => 'Yemen, Sana\'a',
                        'status' => $status,
                        'created_by' => $manager?->id,
                    ]
                );

                $this->seedOwners($merchant->id, $template['en']);
                $this->seedCompany($merchant->id, $template['en'], $bank->id, $i, $status === 'ACTIVE');
            }
        });
    }

    /**
     * Seed merchant owners with ownership percentages summing to exactly 100.
     */
    private function seedOwners(int $merchantId, string $enName): void
    {
        $owners = [
            ['name' => fake()->name(), 'ownership_percentage' => 60.00],
            ['name' => fake()->name(), 'ownership_percentage' => 40.00],
        ];

        foreach ($owners as $owner) {
            MerchantOwner::query()->updateOrCreate(
                ['merchant_id' => $merchantId, 'name' => $owner['name']],
                ['ownership_percentage' => $owner['ownership_percentage']],
            );
        }
    }

    private function seedCompany(int $merchantId, string $enName, int $bankId, int $index, bool $isActive): void
    {
        MerchantCompany::query()->updateOrCreate(
            ['commercial_registration_number' => 'CR-'.$bankId.'-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT)],
            [
                'merchant_id' => $merchantId,
                'name' => $enName,
                'commercial_registration_expiry' => now()->addMonths(random_int(6, 48))->toDateString(),
                'is_active' => $isActive,
            ],
        );
    }
}
