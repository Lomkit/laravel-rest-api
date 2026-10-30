<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Draft> */
#[UseModel(Draft::class)]
class DraftFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'title' => 'Untitled draft',
        ];
    }
}
