<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\Search\Search;

class SearchRequest extends RestRequest
{
    /**
     * Define the validation rules for the search request.
     *
     * @return array
     */
    public function rules()
    {
        $resource = $this->context()->resource($this->route()->controller::$resource);

        return [
            'search' => ['sometimes', 'array', (new Search)->setResource($resource)],
        ];
    }
}
