<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Requests\Traits\ParsesSearch;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Search\SearchNode;

class SearchRequest extends RestRequest
{
    use ParsesSearch;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Search;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return $this->searchRules($this->context()->resource($this->controllerResourceClass()));
    }

    public function payload(): SearchNode
    {
        return $this->searchNode();
    }
}
