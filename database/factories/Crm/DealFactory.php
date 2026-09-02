<?php

namespace Database\Factories\Crm;

use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Models\Crm\Company;
use App\Models\Crm\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->randomElement(['Migrace e-shopu', 'Nový web', 'Audit měření', 'Napojení Pohody']),
            'package' => fake()->randomElement(DealPackage::cases()),
            'value_czk' => fake()->numberBetween(20, 250) * 1000,
            'stage' => DealStage::Lead,
        ];
    }
}
