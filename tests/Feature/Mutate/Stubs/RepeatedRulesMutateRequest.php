<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

class RepeatedRulesMutateRequest extends PayloadReadingMutateRequest
{
    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        return [...parent::rules(), ...parent::rules()];
    }
}
