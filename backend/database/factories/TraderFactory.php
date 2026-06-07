<?php

namespace Database\Factories;

use App\Models\Trader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trader>
 */
class TraderFactory extends Factory
{
    protected $model = Trader::class;

    public function definition(): array
    {
        $names = [
            'شركة صنعاء للتجارة والاستيراد',
            'مؤسسة عدن للمواد الغذائية',
            'مجموعة حضرموت التجارية',
            'شركة تعز للصناعات الخفيفة',
            'مؤسسة الحديدة للاستيراد',
            'شركة المكلا للحبوب',
        ];

        return [
            'tax_number' => fake()->unique()->numerify('YE-TAX-########'),
            'trader_name' => fake()->randomElement($names),
            'tax_card_expiry' => fake()->dateTimeBetween('+6 months', '+4 years')->format('Y-m-d'),
            'commercial_registration_number' => fake()->unique()->numerify('CR-YE-########'),
            'commercial_registration_expiry' => fake()->dateTimeBetween('+6 months', '+4 years')->format('Y-m-d'),
        ];
    }
}
