<?php

namespace Database\Factories\Crm;

use App\Enums\Crm\DemandSource;
use App\Enums\Crm\DemandStatus;
use App\Enums\Crm\Priority;
use App\Models\Crm\Demand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Demand>
 */
class DemandFactory extends Factory
{
    protected $model = Demand::class;

    public function definition(): array
    {
        return [
            'source' => DemandSource::Webtrh,
            'url' => 'https://www.webtrh.cz/poptavka/'.fake()->unique()->numberBetween(1000, 9999),
            'title' => 'Poptávka na úpravy e-shopu',
            'summary' => fake()->sentence(14),
            'posted_at' => now()->subDays(1),
            'priority' => Priority::B,
            'status' => DemandStatus::New,
        ];
    }
}
