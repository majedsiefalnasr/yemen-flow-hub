<?php

namespace Database\Seeders;

use App\Models\Trader;
use Illuminate\Database\Seeder;

class TraderSeeder extends Seeder
{
    public function run(): void
    {
        collect($this->traders())->each(function (array $template): void {
            $trader = Trader::query()->updateOrCreate(
                ['tax_number' => $template['tax_number']],
                [
                    'trader_name' => $template['trader_name'],
                    'tax_card_expiry' => $template['tax_card_expiry'],
                    'commercial_registration_number' => $template['commercial_registration_number'],
                    'commercial_registration_expiry' => $template['commercial_registration_expiry'],
                ]
            );

            foreach ($template['companies'] as $companyName) {
                $trader->companies()->updateOrCreate(
                    ['company_name' => $companyName],
                    ['company_name' => $companyName]
                );
            }

            foreach ($template['owners'] as $owner) {
                $trader->owners()->updateOrCreate(
                    ['full_name' => $owner['full_name']],
                    [
                        'ownership_percentage' => $owner['ownership_percentage'],
                        'nationality' => $owner['nationality'],
                        'identification_number' => $owner['identification_number'],
                    ]
                );
            }
        });
    }

    private function traders(): array
    {
        return [
            [
                'tax_number' => 'YE-TAX-170001',
                'trader_name' => 'شركة صنعاء للتجارة والاستيراد',
                'tax_card_expiry' => '2028-12-31',
                'commercial_registration_number' => 'CR-YE-170001',
                'commercial_registration_expiry' => '2028-12-31',
                'companies' => ['شركة صنعاء للمواد الغذائية', 'مؤسسة التحرير للتوزيع'],
                'owners' => [
                    ['full_name' => 'Ahmed Ali Al-Sanaani', 'ownership_percentage' => 55.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170001'],
                    ['full_name' => 'Mona Saleh Al-Haddad', 'ownership_percentage' => 45.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170002'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170002',
                'trader_name' => 'مؤسسة عدن للمواد الغذائية',
                'tax_card_expiry' => '2029-06-30',
                'commercial_registration_number' => 'CR-YE-170002',
                'commercial_registration_expiry' => '2029-06-30',
                'companies' => ['شركة عدن للتوريد'],
                'owners' => [
                    ['full_name' => 'Nabil Omar Al-Adeni', 'ownership_percentage' => 70.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170003'],
                    ['full_name' => 'Samir Hadi Mansour', 'ownership_percentage' => 30.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170004'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170003',
                'trader_name' => 'مجموعة حضرموت التجارية',
                'tax_card_expiry' => '2028-09-15',
                'commercial_registration_number' => 'CR-YE-170003',
                'commercial_registration_expiry' => '2028-09-15',
                'companies' => ['حضرموت للتجارة العامة', 'المكلا لخدمات الاستيراد'],
                'owners' => [
                    ['full_name' => 'Fahd Salem Ba-Wazir', 'ownership_percentage' => 40.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170005'],
                    ['full_name' => 'Huda Omar Ba-Faqih', 'ownership_percentage' => 35.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170006'],
                    ['full_name' => 'Yasser Mohammed Saeed', 'ownership_percentage' => 25.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170007'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170004',
                'trader_name' => 'شركة تعز للصناعات الخفيفة',
                'tax_card_expiry' => '2030-01-31',
                'commercial_registration_number' => 'CR-YE-170004',
                'commercial_registration_expiry' => '2030-01-31',
                'companies' => ['تعز للتجهيزات الصناعية'],
                'owners' => [
                    ['full_name' => 'Khaled Abdullah Al-Taizi', 'ownership_percentage' => 100.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170008'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170005',
                'trader_name' => 'مؤسسة الحديدة للاستيراد',
                'tax_card_expiry' => '2029-03-20',
                'commercial_registration_number' => 'CR-YE-170005',
                'commercial_registration_expiry' => '2029-03-20',
                'companies' => ['الحديدة للخدمات التجارية', 'شركة البحر الأحمر للتوريد'],
                'owners' => [
                    ['full_name' => 'Tariq Hassan Al-Hodeidi', 'ownership_percentage' => 60.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170009'],
                    ['full_name' => 'Aisha Mahmoud Saleh', 'ownership_percentage' => 40.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170010'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170006',
                'trader_name' => 'شركة إب للتجارة العامة',
                'tax_card_expiry' => '2028-11-10',
                'commercial_registration_number' => 'CR-YE-170006',
                'commercial_registration_expiry' => '2028-11-10',
                'companies' => ['إب للتجارة والتوزيع'],
                'owners' => [
                    ['full_name' => 'Mohammed Naji Al-Ibbani', 'ownership_percentage' => 80.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170011'],
                    ['full_name' => 'Rami Abdulrahman Ali', 'ownership_percentage' => 20.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170012'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170007',
                'trader_name' => 'مجموعة سبأ للاستيراد',
                'tax_card_expiry' => '2029-08-25',
                'commercial_registration_number' => 'CR-YE-170007',
                'commercial_registration_expiry' => '2029-08-25',
                'companies' => ['سبأ للمواد الإنشائية', 'سبأ للمعدات'],
                'owners' => [
                    ['full_name' => 'Abdulaziz Yahya Al-Sabaei', 'ownership_percentage' => 50.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170013'],
                    ['full_name' => 'Hassan Saleh Murad', 'ownership_percentage' => 30.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170014'],
                    ['full_name' => 'Lina Mohammed Noman', 'ownership_percentage' => 20.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170015'],
                ],
            ],
            [
                'tax_number' => 'YE-TAX-170008',
                'trader_name' => 'شركة المكلا للحبوب',
                'tax_card_expiry' => '2030-04-05',
                'commercial_registration_number' => 'CR-YE-170008',
                'commercial_registration_expiry' => '2030-04-05',
                'companies' => ['المكلا للحبوب والأعلاف'],
                'owners' => [
                    ['full_name' => 'Omar Salem Al-Mukalli', 'ownership_percentage' => 75.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170016'],
                    ['full_name' => 'Mariam Ali Baras', 'ownership_percentage' => 25.00, 'nationality' => 'Yemeni', 'identification_number' => 'ID-170017'],
                ],
            ],
        ];
    }
}
