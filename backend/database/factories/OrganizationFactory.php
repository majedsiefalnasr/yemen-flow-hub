<?php

namespace Database\Factories;

use App\Enums\OrganizationClassification;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(),
            'name' => fake()->company(),
            'classification' => OrganizationClassification::OTHER,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
