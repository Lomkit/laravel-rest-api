<?php

namespace Lomkit\Rest\Http\Requests;

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
                'required', 'array',
            ],
        ];
    }
}
