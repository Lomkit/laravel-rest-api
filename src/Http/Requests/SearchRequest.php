<?php

namespace Lomkit\Rest\Http\Requests;

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
        return [
            'search' => new SearchRules(
                $this->route()->controller::newResource(),
                $this,
                true
            ),
        ];
    }
}
