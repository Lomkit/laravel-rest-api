<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'team' => 'red',
            'title' => 'Roadmap',
            'pinned' => false,
        ];
    }
}
