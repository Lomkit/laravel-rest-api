<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class ReadDocumentFields extends Action
{
    /** @var list<array{fields: ActionFields, keys: list<int>}> */
    public static array $runs = [];

    /** @var list<ActionFields> */
    public static array $batches = [];

    #[\Override]
    public function abilities(): array
    {
        return [];
    }

    #[\Override]
    public function fields(): array
    {
        return [
            DateTime::make('due_at'),
            Enum::make('priority', DocumentPriority::class)->default(DocumentPriority::Normal),
            Integer::make('copies'),
            Json::make('labels'),
            Number::make('rate'),
            Text::make('note')->nullable(),
            Text::make('reason'),
        ];
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = ['fields' => $fields, 'keys' => $models->modelKeys()];
    }
}
