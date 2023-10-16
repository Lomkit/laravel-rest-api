<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\ArrayWith;
use Lomkit\Rest\Rules\CustomRulable;
use Lomkit\Rest\Rules\MutateRules;
use Lomkit\Rest\Rules\SearchRules;

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
            'mutate.*' => new MutateRules(
                $this->route()->controller::newResource(),
                $this
            ),
        ];
    }
}
