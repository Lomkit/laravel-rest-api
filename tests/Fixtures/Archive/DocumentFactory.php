<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title' => 'Annual report',
            'archived' => false,
        ];
    }
}
