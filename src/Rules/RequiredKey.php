<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RequiredKey implements DataAwareRule, ValidationRule
{
    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * All the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function __construct(
        public string $otherField
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $arrayDot = Arr::dot($this->data);
        $index = Str::after($attribute, 'mutate.')[0];
        $operation = $arrayDot[
            Str::of($attribute)
                ->beforeLast('.')
                ->beforeLast('.')
                ->append('.'.$index.'.operation')
                ->toString()
        ] ?? null;

        if (
            in_array($operation, ['update', 'attach', 'detach', 'toggle', 'sync']) &&
            !isset($arrayDot[$attribute]) &&
            !isset($arrayDot[Str::replace('*', $index, $this->otherField)])
        ) {
            $fail("The $attribute field is required when the operation is '$operation' and the $this->otherField field is missing.");
        }
    }
}