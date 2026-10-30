<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Rules\Search\Search;
use Lomkit\Rest\Rules\Search\SearchText;

class SearchRequest extends RestRequest
{
    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Search;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $resource = $this->context()->resource($this->controllerResourceClass());
        $textSearch = $this->context()->isTextSearch();

        return [
            'search' => ['sometimes', 'array', (new Search)->setResource($resource)->forTextSearch($textSearch)],
            'search.text' => ['sometimes', 'array', (new SearchText)->setResource($resource)->forTextSearch($textSearch)],
        ];
    }
}
