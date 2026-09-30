<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;

trait Rulable
{
    /**
     * Get the validation rules for resource requests.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function rules(RestRequest $request)
    {
        return [];
    }

    /**
     * Get the validation rules for resource creation requests.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function createRules(RestRequest $request)
    {
        return [];
    }

    /**
     * Get the validation rules for resource update requests.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function updateRules(RestRequest $request)
    {
        return [];
    }

    /**
     * Inspect a search as a whole once it has passed its rules,
     * errors added to the validator are returned as validation errors.
     *
     * This is not called for a request carrying no search.
     *
     * @param RestRequest $request
     * @param Validator   $validator
     *
     * @return void
     */
    public function afterSearchValidation(RestRequest $request, Validator $validator): void
    {
        //
    }
}
