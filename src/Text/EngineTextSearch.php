<?php

namespace Lomkit\Rest\Text;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineQuery;

interface EngineTextSearch extends TextSearch
{
    public function query(TextQuery $text, TextTarget $target, RestRequest $request, ?Trashed $trashed = null): EngineQuery;
}
