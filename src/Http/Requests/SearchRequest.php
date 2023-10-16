<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\AggregateField;
use Lomkit\Rest\Rules\AggregateFilterable;
use Lomkit\Rest\Rules\Includable;
use Lomkit\Rest\Rules\Instruction;
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
            'search' =>
                new SearchRules(
                    $this->route()->controller::newResource(),
                    $this,
                    true
                )
        ];
    }
}
