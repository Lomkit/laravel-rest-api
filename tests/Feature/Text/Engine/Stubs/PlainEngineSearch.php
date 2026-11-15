<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\EngineTextSearch;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;

final readonly class PlainEngineSearch implements EngineTextSearch
{
    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return $this->index($target)->capabilities();
    }

    #[\Override]
    public function query(TextQuery $text, TextTarget $target, ?Trashed $trashed = null): EngineQuery
    {
        return new EngineQuery($this->index($target), $text, $trashed);
    }

    private function index(TextTarget $target): EngineIndex
    {
        $model = ScoutModel::tryFrom($target->model) ?? throw InvalidTextSearchException::unsearchableModel($target->resource, $target->model::class);
        $profile = new PlainProfile;

        return new EngineIndex($model, $profile, IndexAttributes::of($target, $profile, ['team'], [], []));
    }
}
