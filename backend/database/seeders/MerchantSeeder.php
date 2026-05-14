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
        ];

        $inactiveBudget = 2;

        Bank::query()->where('is_active', true)->orderBy('id')->get()->each(function (Bank $bank) use (&$inactiveBudget, $templates): void {
            $manager = User::query()
                ->where('bank_id', $bank->id)
                ->where('role', 'BANK_MANAGER')
                ->first();

            foreach ($templates as $i => $template) {
                $isActive = true;
                if ($inactiveBudget > 0 && (($bank->id + $i) % 5 === 0)) {
                    $isActive = false;
                    $inactiveBudget--;
                }

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
                        'is_active' => $isActive,
                        'created_by' => $manager?->id,
                    ]
                );
            }
        });
    }
}

