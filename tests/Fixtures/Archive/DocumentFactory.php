<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
#[UseModel(Document::class)]
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Annual report',
            'archived' => false,
        ];
    }
}
