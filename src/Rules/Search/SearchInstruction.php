<?php

namespace Lomkit\Rest\Rules\Search;

use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Rules\Distinct;
use Lomkit\Rest\Rules\Input\InputFields;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Validation\RuleCompiler;

class SearchInstruction extends RestRule
{
    /** @var array<int, Instruction>|null */
    private ?array $instructions = null;

    /** @param array<int, Instruction> $instructions */
    public function among(array $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

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
        return array_values(array_map(fn (Instruction $instruction): string => $instruction->uriKey(), $this->declared()));
    }

    private function fields(mixed $value): ?FieldSet
    {
        $name = is_array($value) ? ($value['name'] ?? null) : null;

        foreach ($this->declared() as $instruction) {
            if ($instruction->uriKey() === $name) {
                return $instruction->getFields($this->resource->context()->request);
            }
        }

        return null;
    }

    /** @return array<int, Instruction> */
    private function declared(): array
    {
        return $this->instructions ?? InstructionSupport::among($this->resource->getInstructions($this->resource->context()->request), TextBackend::Query);
    }
}
