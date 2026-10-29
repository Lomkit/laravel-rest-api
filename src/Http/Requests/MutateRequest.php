<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\Mutate\Mutate;

class MutateRequest extends RestRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $resource = $this->context()->resource($this->controllerResourceClass());

        return [
            'mutate' => ['required', 'array'],
            'mutate.*' => (new Mutate)->setResource($resource),
        ];
    }
}
