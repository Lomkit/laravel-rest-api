<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Rulable
{
    /**
     * Get the validation rules for resource requests.
     *
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
     *
     * @return array
     */
    public function updateRules(RestRequest $request)
    {
        return [];
    }
}
