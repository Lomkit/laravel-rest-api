<?php

namespace Lomkit\Rest\Rules;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\RuleCompiler;

abstract class RestRule
{
    protected Resource $resource;

    public function setResource(Resource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        $into->add($this->buildValidationRules($attribute, $value));
    }

    /** @return array<string, mixed> */
    abstract public function buildValidationRules(string $attribute, mixed $value): array;
}
