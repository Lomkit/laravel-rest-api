<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Requests\Traits\ValidatesResourceKeys;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;

class ForceDestroyRequest extends RestRequest
{
    use ValidatesResourceKeys;

    public function endpoint(): Endpoint
    {
        return Endpoint::ForceDestroy;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->forceDestroyRules($this->context()->resource($this->controllerResourceClass()));
    }

    /** @return array<string, mixed> */
    public function forceDestroyRules(Resource $resource): array
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
