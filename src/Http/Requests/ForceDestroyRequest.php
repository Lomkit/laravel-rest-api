<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Requests\Traits\ValidatesResourceKeys;
use Lomkit\Rest\Http\Resource;

class ForceDestroyRequest extends RestRequest
{
    use ValidatesResourceKeys;

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
        ];
    }
}
