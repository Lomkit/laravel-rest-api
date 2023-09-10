<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Resource;

class RestoreRequest extends RestRequest
{
    public function rules()
    {
        return $this->restoreRules($this->route()->controller::newResource());
    }

    public function restoreRules(Resource $resource)
    {
        return [
            'resources' => [
                'required', 'array',
            ],
        ];
    }
}
