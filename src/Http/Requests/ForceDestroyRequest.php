<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Resource;

class ForceDestroyRequest extends RestRequest
{
    /**
     * Define the validation rules for the force destroy request.
     *
     * @return array
     *
     * This method defines the validation rules for force destroying resources.
     * It requires an array of resources to be force destroyed.
     */
    public function rules()
    {
        return $this->forceDestroyRules($this->route()->controller::newResource());
    }

    /**
     * Define the validation rules for force destroying resources.
     *
     * @param resource $resource
     *
     * @return array
     *
     * This method specifies the validation rules for force destroying resources.
     * It expects an instance of the resource being force destroyed and requires an array
     * containing the resources to be force destroyed.
     */
    public function forceDestroyRules(Resource $resource)
    {
        return [
            'resources' => [
                'required', 'array',
            ],
        ];
    }
}
