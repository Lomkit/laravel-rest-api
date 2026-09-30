<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Support\Rest\Filters\NameOrStringFilter;

class ModelWithFiltersResource extends ModelResource
{
    public function filters(RestRequest $request): array
    {
        return [
            NameOrStringFilter::make('name_or_string'),
            // Declared under the name of a field of the resource
            NameOrStringFilter::make('unique'),
        ];
    }

    public function relations(RestRequest $request): array
    {
        return array_map(function (Relation $relation) {
            return $relation->relation === 'hasManyRelation' ? $relation->negationsAsAbsence() : $relation;
        }, parent::relations($request));
    }

    public function afterSearchValidation(RestRequest $request, Validator $validator): void
    {
        $fields = array_column($request->input('search.filters', []), 'field');

        if (in_array('name', $fields) && in_array('name_or_string', $fields)) {
            $validator->errors()->add('search.filters', 'The name and name_or_string filters cannot be combined.');
        }
    }
}
