<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\Search\Search;
use Lomkit\Rest\Rules\SearchRules;

class SearchRequest extends RestRequest
{
    /**
     * Define the validation rules for the search request.
     *
     * @return array
     */
    public function rules()
    {
        $resource = $this->route()->controller::newResource();

        return [
            'search' => (new Search)->setResource($resource),
        ];
    }
}
