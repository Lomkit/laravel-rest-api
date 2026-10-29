<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\Search\Search;

class SearchRequest extends RestRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $resource = $this->context()->resource($this->controllerResourceClass());

        return [
            'search' => ['sometimes', 'array', (new Search)->setResource($resource)],
        ];
    }
}
