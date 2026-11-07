<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Closure;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class TouchDocuments extends Action
{
    /** @var list<list<int>> */
    public static array $runs = [];

    /** @var list<array{fields: ActionFields, impacted: int, runs: int}> */
    public static array $responds = [];

    public static ?ActionFields $handled = null;

    /** @var (Closure(ActionFields, int): ?ActionResponse)|null */
    public static ?Closure $answer = null;

    public int $chunkCount = 4;

    public static function reset(): void
    {
        self::$runs = [];
        self::$responds = [];
        self::$handled = null;
        self::$answer = null;
        TouchDocumentResource::$target = ActionTarget::Search;
    }

    #[\Override]
    public function abilities(): array
    {
        return [];
    }

    #[\Override]
    public function fields(): array
    {
        return [Text::make('note')->nullable()];
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = $models->modelKeys();
        self::$handled = $fields;
    }

    #[\Override]
    public function respond(ActionFields $fields, int $impacted): ?ActionResponse
    {
        self::$responds[] = ['fields' => $fields, 'impacted' => $impacted, 'runs' => count(self::$runs)];

        return self::$answer === null ? null : (self::$answer)($fields, $impacted);
    }
}
