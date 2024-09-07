<?php

namespace Lomkit\Rest\Http\Requests;

use Lomkit\Rest\Rules\MutateRules;

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
        return [
            'mutate' => ['required'],
            'mutate.*' => new MutateRules(
                $this->route()->controller::newResource(),
                $this,
                null,
                true
            ),
        ];
    }
}
