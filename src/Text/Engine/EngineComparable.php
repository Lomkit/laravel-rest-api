<?php

namespace Lomkit\Rest\Text\Engine;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class EngineComparable implements ValidationRule
{
    public function __construct(private IndexAttribute $attribute) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $typed = EngineValue::of($this->attribute->field, $value);

        if ($typed === null) {
            $fail('rest::validation.engine_value')->translate();

            return;
        }

        if (! LiteralValue::accepts($typed)) {
            $fail('rest::validation.engine_characters')->translate();
        }
    }
}
