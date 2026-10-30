<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Note> */
#[UseModel(Note::class)]
class NoteFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'team' => 'red',
            'title' => 'Roadmap',
            'pinned' => false,
        ];
    }
}
