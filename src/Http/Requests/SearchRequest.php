<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\Search\Search;

class SearchRequest extends RestRequest
{
    /**
     * Normalize the search input before validation.
     * Removes empty or whitespace-only text values to prevent
     * invalid Elasticsearch queries being sent by ScoutBuilder.
     */
    protected function prepareForValidation(): void
    {
        $search = $this->input('search', []);

        if (trim(($search['text']['value'] ?? '')) === '') {
            unset($search['text']);
            $this->merge(['search' => $search]);
        }
    }

    /**
     * Define the validation rules for the search request.
     *
     * @return array
     */
    public function rules()
    {
        $resource = $this->route()->controller::newResource();

        return [
            'search' => (new Search())->setResource($resource),
        ];
    }
}
