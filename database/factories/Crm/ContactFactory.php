<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['jednatel', 'marketing', 'provoz']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+420 '.fake()->numerify('### ### ###'),
            'is_primary' => true,
        ];
    }
}
