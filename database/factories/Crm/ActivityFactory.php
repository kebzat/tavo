<?php

namespace Database\Factories\Crm;

use App\Enums\Crm\ActivityType;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => ActivityType::Email,
            'subject' => 'První oslovení',
            'body' => fake()->sentence(12),
            'happened_at' => now(),
        ];
    }
}
