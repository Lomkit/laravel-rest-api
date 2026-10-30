<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\Distinct;
use Lomkit\Rest\Rules\Input\InputFields;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;

class SearchInstruction extends RestRule
{
    use DependsOnTextSearch;

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        $fields = $this->fields($value);

        $into->add($this->rulesFor($attribute, $fields, $value));

        if ($fields === null || ! is_array($value)) {
            return;
        }

        (new InputFields($fields))->compile($into, $attribute.'.fields', $value['fields'] ?? []);
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        return $this->rulesFor($attribute, $this->fields($value), $value);
    }

    /** @return array<string, list<mixed>> */
    private function rulesFor(string $attribute, ?FieldSet $fields, mixed $value): array
    {
        return [
            $attribute.'.name' => [new OneOf($this->names()), 'required', 'string'],
            $attribute.'.fields' => ['sometimes', 'array'],
            $attribute.'.fields.*.name' => $fields === null ? [] : [
                'required',
                'string',
                Distinct::among(is_array($value) ? ($value['fields'] ?? null) : null, 'name'),
                new OneOf($fields->names()),
            ],
        ];
    }

    /** @return list<string> */
    private function names(): array
    {
        $request = $this->resource->context()->request;
        $instructions = $this->textSearch ? $this->resource->getScoutInstructions($request) : $this->resource->getInstructions($request);

        return array_values(array_map(fn (Instruction $instruction): string => $instruction->uriKey(), $instructions));
    }

    private function fields(mixed $value): ?FieldSet
    {
        $request = $this->resource->context()->request;
        $name = is_array($value) ? ($value['name'] ?? null) : null;

        if (! is_string($name)) {
            return null;
        }

        $instruction = $this->textSearch ? $this->resource->scoutInstruction($request, $name) : $this->resource->instruction($request, $name);

        return $instruction?->getFields($request);
    }
}
