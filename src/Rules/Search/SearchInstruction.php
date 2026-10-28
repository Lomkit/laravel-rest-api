<?php

namespace Lomkit\Rest\Rules\Search;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\Concerns\ValidatesFields;
use Lomkit\Rest\Rules\RestRule;

class SearchInstruction extends RestRule
{
    use ValidatesFields;

    /** @return array<string, list<mixed>> */
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $instruction = $this->instruction($value);

        return [
            $attribute.'.name' => [Rule::in($this->names()), 'required', 'string'],
            $attribute.'.fields' => ['sometimes', 'array'],
            $attribute.'.fields.*.name' => $instruction === null ? [] : [
                'required',
                'string',
                Rule::in($instruction->getFields($this->resource->context()->request)->names()),
            ],
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        parent::validate($attribute, $value, $fail);

        $instruction = $this->instruction($value);

        if ($instruction === null || ! is_array($value)) {
            return;
        }

        $this->validateFields(
            $this->validator,
            $attribute.'.fields',
            $value['fields'] ?? [],
            $instruction->getFields($this->resource->context()->request),
        );
    }

    /** @return list<string> */
    private function names(): array
    {
        $request = $this->resource->context()->request;
        $instructions = $request->isScoutMode() ? $this->resource->getScoutInstructions($request) : $this->resource->getInstructions($request);

        return array_values(array_map(fn (Instruction $instruction): string => $instruction->uriKey(), $instructions));
    }

    private function instruction(mixed $value): ?Instruction
    {
        $request = $this->resource->context()->request;
        $name = is_array($value) ? ($value['name'] ?? null) : null;

        if (! is_string($name)) {
            return null;
        }

        return $request->isScoutMode() ? $this->resource->scoutInstruction($request, $name) : $this->resource->instruction($request, $name);
    }
}
