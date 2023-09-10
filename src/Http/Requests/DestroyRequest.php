<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Resource;

class DestroyRequest extends RestRequest
{
    public function rules()
    {
        return $this->destroyRules($this->route()->controller::newResource());
    }

    public function destroyRules(Resource $resource)
    {
        return [
            'resources' => [
                'required', 'array',
            ],
        ];
    }
}
