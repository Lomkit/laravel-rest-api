<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

class ForceDestroyRequest extends RestRequest
{
    public function rules()
    {
        return $this->forceDestroyRules($this->route()->controller::newResource());
    }

    public function forceDestroyRules(Resource $resource)
    {
        return [
            'resources' => [
                'required', 'array'
            ]
        ];
    }
}
