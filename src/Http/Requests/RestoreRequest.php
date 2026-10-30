<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Requests\Traits\ValidatesResourceKeys;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;

class RestoreRequest extends RestRequest
{
    use ValidatesResourceKeys;

    public function endpoint(): Endpoint
    {
        return Endpoint::Restore;
    }

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
            'resources.*' => $this->keyRules($resource),
            'search' => ['bail', 'prohibited', 'array'],
        ];
    }
}
