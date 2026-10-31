<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Rules\Search\Search;

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

        return [
            'search' => ['sometimes', 'array', (new Search)->setResource($resource)],
        ];
    }
}
