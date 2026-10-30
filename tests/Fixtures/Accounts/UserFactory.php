<?php

namespace Lomkit\Rest\Tests\Fixtures\Accounts;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
#[UseModel(User::class)]
class UserFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret',
            'is_staff' => false,
        ];
    }

    public function staff(): static
    {
        return $this->state([
            'name' => 'Grace',
            'email' => 'grace@example.com',
            'is_staff' => true,
        ]);
    }
}
