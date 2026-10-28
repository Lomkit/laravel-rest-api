<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\Mutate\Mutate;

class MutateRequest extends RestRequest
{
    /**
     * Define the validation rules for the mutate request.
     *
     * @return array
     *
     * This method defines the validation rules for mutating resources, such as create, update, attach, or detach.
     * It includes rules for the operation type, attributes, and relations.
     */
    public function rules()
    {
        $resource = $this->context()->resource($this->route()?->controller::$resource);

        return [
            'mutate' => ['required', 'array'],
            'mutate.*' => (new Mutate)->setResource($resource),
        ];
    }
}
