<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

class DestroyRequest extends RestRequest
{
    /**
     * Define the validation rules for the destroy request.
     *
     * @return array
     *
     * This method defines the validation rules for destroying resources.
     * It requires an array of resources to be destroyed.
     */
    public function rules()
    {
        return $this->destroyRules($this->route()->controller::newResource());
    }

    /**
     * Define the validation rules for destroying resources.
     *
     * @param Resource $resource
     * @return array
     *
     * This method specifies the validation rules for destroying resources.
     * It expects an instance of the resource being destroyed and requires an array
     * containing the resources to be destroyed.
     */
    public function destroyRules(Resource $resource)
    {
        return [
            'resources' => [
                'required', 'array'
            ]
        ];
    }
}
