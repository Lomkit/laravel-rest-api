<?php

namespace Lomkit\Rest\Text;

use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineQuery;

/** @internal */
interface ListingTextSearch extends EngineTextSearch
{
    public function servingLists(): bool;

    public function list(TextTarget $target, ?Trashed $trashed = null): EngineQuery;
}
