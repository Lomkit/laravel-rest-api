<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;

class RecordNotes extends Action
{
    /** @var list<array{keys: list<int>, reason: mixed}> */
    public static array $runs = [];

    #[\Override]
    public function fields(): array
    {
        return [Text::make('reason')->nullable()];
    }

    /** @param Collection<int, Note> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = ['keys' => array_values($models->modelKeys()), 'reason' => $fields->reason];
    }
}
