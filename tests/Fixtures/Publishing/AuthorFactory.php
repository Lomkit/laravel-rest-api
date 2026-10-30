<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    protected $model = Author::class;

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
