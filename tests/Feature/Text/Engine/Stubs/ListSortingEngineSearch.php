<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\EngineTextSearch;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;

final readonly class ListSortingEngineSearch implements EngineTextSearch
{
    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        $capabilities = (new PlainEngineSearch)->capabilities($target);
        $engine = $capabilities->engine ?? new EngineGrammar([], []);

        return $capabilities->withEngine(new EngineGrammar($engine->filters, $engine->sorts, listSortKeys: PHP_INT_MAX));
    }

    #[\Override]
    public function query(TextQuery $text, TextTarget $target, ?Trashed $trashed = null): EngineQuery
    {
        return (new PlainEngineSearch)->query($text, $target, $trashed);
    }
}
