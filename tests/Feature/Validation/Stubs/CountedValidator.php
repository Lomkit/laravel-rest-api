<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Validation\Validator;

class CountedValidator extends Validator
{
    private ValidationCount $count;

    public function countIn(ValidationCount $count): static
    {
        $this->count = $count;
        $count->validators++;

        return $this;
    }

    public function passes(): bool
    {
        $this->count->passes++;

        return parent::passes();
    }
}
