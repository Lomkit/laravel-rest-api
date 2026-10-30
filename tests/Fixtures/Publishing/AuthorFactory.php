<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Author> */
#[UseModel(Author::class)]
class AuthorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'settings' => null,
            'api_token' => 'ada-token',
        ];
    }
}
