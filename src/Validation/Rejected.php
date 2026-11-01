<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class Rejected implements ValidationRule
{
    public bool $implicit;

    public function __construct(public Violation $violation)
    {
        $this->implicit = true;
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fail($this->violation->message)->translate($this->violation->replace);
    }
}
