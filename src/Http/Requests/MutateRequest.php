<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Rules\MaxItems;
use Lomkit\Rest\Rules\Mutate\Mutate;

class MutateRequest extends RestRequest
{
    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Mutate;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $resource = $this->context()->resource($this->controllerResourceClass());

        return [
            'mutate' => ['required', 'array', new MaxItems($this->context()->limits()->sizes->mutate)],
            'mutate.*' => (new Mutate)->setResource($resource),
        ];
    }
}
