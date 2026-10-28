<?php

namespace Lomkit\Rest\Tests\Fixtures\Accounts;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

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
