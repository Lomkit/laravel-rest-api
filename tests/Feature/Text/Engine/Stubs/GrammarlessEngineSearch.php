<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\EngineTextSearch;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;

final readonly class GrammarlessEngineSearch implements EngineTextSearch
{
    public function __construct(private ScoutEngine $engine) {}

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        $capabilities = $this->engine->capabilities($target);

        return new TextCapabilities([], $capabilities->modes, $capabilities->relevance, $capabilities->trashed);
    }

    #[\Override]
    public function query(TextQuery $text, TextTarget $target, RestRequest $request, ?Trashed $trashed = null): EngineQuery
    {
        return $this->engine->query($text, $target, $request, $trashed);
    }
}
