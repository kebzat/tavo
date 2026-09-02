<?php

namespace Database\Factories\Crm;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\Priority;
use App\Models\Crm\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'website' => Str::slug($name).'.cz',
            'city' => fake()->randomElement(['Hradec Králové', 'Pardubice', 'Chrudim', 'Jičín', 'Náchod']),
            'industry' => fake()->randomElement(['gastro', 'stavebnictví', 'zdravotnictví', 'e-commerce', 'služby']),
            'segment' => fake()->randomElement(CompanySegment::cases()),
            'priority' => fake()->randomElement(Priority::cases()),
            'source' => CompanySource::Research,
            'status' => CompanyStatus::New,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => CompanyStatus::FollowUp,
            'next_action_at' => now()->subDays(3)->setTime(9, 0),
        ]);
    }
}
