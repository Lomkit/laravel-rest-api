<?php

namespace Lomkit\Rest\Tests\Unit\Fields\Stubs;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StartsWithCapital implements ValidationRule
{
    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void {}
}
