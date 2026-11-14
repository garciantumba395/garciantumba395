<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        // Numéros fictifs au format valide RDC (mobile) ; jamais de vrais numéros.
        return [
            'name' => fake()->name(),
            'phone' => '+243'.fake()->unique()->numerify('81#######'),
            'email' => null,
        ];
    }
}
