<?php

namespace Lomkit\Rest\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

interface AllowedValues extends ValidationRule
{
    public function accepts(mixed $value): bool;
}
