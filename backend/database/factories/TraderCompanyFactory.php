<?php

namespace Database\Factories;

use App\Models\Trader;
use App\Models\TraderCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraderCompany>
 */
class TraderCompanyFactory extends Factory
{
    protected $model = TraderCompany::class;

    public function definition(): array
    {
        $companies = [
            'الشركة اليمنية للتوريد',
            'مؤسسة الجزيرة التجارية',
            'شركة النور للاستيراد',
            'مجموعة سبأ للتجارة',
            'شركة الميناء للخدمات التجارية',
        ];

        return [
            'trader_id' => Trader::factory(),
            'company_name' => fake()->randomElement($companies),
        ];
    }
}
