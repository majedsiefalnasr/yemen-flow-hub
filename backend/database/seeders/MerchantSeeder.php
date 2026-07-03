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
        // Four merchants per bank. tax_number and commercial_registration_number
        // are unique keys, so each template's base value is offset per bank to
        // avoid collisions across the two banks.
        $templates = [
            ['ar' => 'مجموعة الشيباني', 'en' => 'Al-Shaibani Group', 'tax' => 4107777, 'cr' => 50013],
            ['ar' => 'شركة ثابت إخوان', 'en' => 'Thabet Brothers Co.', 'tax' => 4115554, 'cr' => 50026],
            ['ar' => 'شركة هائل سعيد أنعم', 'en' => 'Hayel Saeed Anam Co.', 'tax' => 4100000, 'cr' => 50000],
            ['ar' => 'مؤسسة النور للاستيراد', 'en' => 'Al-Noor Import Est.', 'tax' => 4102222, 'cr' => 50002],
        ];

        Bank::query()->where('is_active', true)->orderBy('id')->get()->each(function (Bank $bank, int $bankIndex) use ($templates): void {
            $manager = User::query()
                ->where('bank_id', $bank->id)
                ->where('role', 'DATA_ENTRY')
                ->first();

            // Per-bank offset keeps tax/CR unique across banks while preserving
            // the readable base values for the first bank.
            $offset = $bankIndex * 100;

            foreach ($templates as $i => $template) {
                $taxNumber = (string) ($template['tax'] + $offset);
                $crNumber = 'CR-'.str_pad((string) ($template['cr'] + $offset), 6, '0', STR_PAD_LEFT);
                $name = "{$template['ar']} / {$template['en']}";

                $merchant = Merchant::query()->updateOrCreate(
                    [
                        'bank_id' => $bank->id,
                        'name' => $name,
                    ],
                    [
                        'tax_number' => $taxNumber,
                        'tax_card_expiry' => now()->addMonths(random_int(12, 36))->toDateString(),
                        'phone' => '+967 7'.random_int(0, 9).' '.random_int(100, 999).' '.random_int(1000, 9999),
                        'address' => 'Yemen, Sana\'a',
                        'status' => 'ACTIVE',
                        'created_by' => $manager?->id,
                    ]
                );

                $this->seedOwners($merchant->id, $template['en']);
                $this->seedCompany($merchant->id, $template['en'], $crNumber);
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

    private function seedCompany(int $merchantId, string $enName, string $crNumber): void
    {
        MerchantCompany::query()->updateOrCreate(
            ['commercial_registration_number' => $crNumber],
            [
                'merchant_id' => $merchantId,
                'name' => $enName,
                'commercial_registration_expiry' => now()->addMonths(random_int(12, 48))->toDateString(),
                'is_active' => true,
            ],
        );
    }
}
