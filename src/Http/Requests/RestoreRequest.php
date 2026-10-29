<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Resource;

class RestoreRequest extends RestRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->restoreRules($this->context()->resource($this->controllerResourceClass()));
    }

    /** @return array<string, mixed> */
    public function restoreRules(Resource $resource): array
    {
        return [
            'resources' => [
                'required', 'array',
            ],
        ];
    }
}
