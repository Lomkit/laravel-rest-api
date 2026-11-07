<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class TouchDocuments extends Action
{
    /** @var list<list<int>> */
    public static array $runs = [];

    public int $chunkCount = 4;

    #[\Override]
    public function abilities(): array
    {
        return [];
    }

    /** @param Collection<int, Document> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = $models->modelKeys();
    }
}
