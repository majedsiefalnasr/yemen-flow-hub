<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->slug(),
            'is_active' => true,
            'organization_id' => Organization::factory(),
        ];
    }
}
