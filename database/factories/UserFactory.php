<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'sub' => Str::uuid()->toString(),
            'name' => fake()->name(),
            'given_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'avatar' => null,
            'groups' => [],
        ];
    }
}
