<?php

namespace Database\Factories;

use App\Models\Trader;
use App\Models\TraderOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraderOwner>
 */
class TraderOwnerFactory extends Factory
{
    protected $model = TraderOwner::class;

    public function definition(): array
    {
        return [
            'trader_id' => Trader::factory(),
            'full_name' => fake()->name(),
            'ownership_percentage' => fake()->randomFloat(2, 1, 100),
            'nationality' => fake()->optional()->randomElement(['Yemeni', 'Egyptian', 'Jordanian', 'Saudi']),
            'identification_number' => fake()->optional()->numerify('ID-##########'),
        ];
    }

    public function requiredOwner(): static
    {
        return $this->state(fn (): array => [
            'ownership_percentage' => fake()->randomFloat(2, 25, 100),
        ]);
    }
}
