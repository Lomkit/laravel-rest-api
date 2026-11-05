<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\InstructionNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class InstructionParser
{
    private const array KEYS = ['name' => true, 'fields' => true];

    /**
     * @param  list<Cursor>  $entries
     * @return list<InstructionNode>
     */
    public function parse(array $entries, ValidationContext $context): array
    {
        if ($entries === []) {
            return [];
        }

        $resource = $context->resource;
        $declared = $context->grammar->instructions($resource->getInstructions());
        $names = $context->vocabulary->of($resource, $context->grammar, 'instructions', fn (): array => array_values(array_map(
            fn (Instruction $instruction): string => $instruction->uriKey(),
            $declared,
        )));
        $instructions = [];

        foreach ($entries as $entry) {
            $instruction = $this->instruction($entry, $declared, $names, $context);

            if ($instruction !== null) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    /** @param array<int, Instruction> $declared */
    private function instruction(Cursor $entry, array $declared, OneOf $names, ValidationContext $context): ?InstructionNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, self::KEYS);

        $name = $entry->at('name');
        $fields = $entry->at('fields');
        $instruction = self::find($declared, $name->value);

        $context->rules->add($name->path, [$names, 'required', 'string']);
        $context->rules->addWhenPresent($fields, ['array']);
        $context->reader->admit($fields, $context->limits->sizes->fields);

        if ($instruction === null) {
            $context->rules->partial();

            return null;
        }

        $inputs = new InputSet($instruction->getFields());

        return new InstructionNode($instruction, $inputs->named($fields, $context, 'fields:'.$instruction->uriKey()));
    }

    /** @param array<int, Instruction> $declared */
    private static function find(array $declared, mixed $name): ?Instruction
    {
        foreach ($declared as $instruction) {
            if ($instruction->uriKey() === $name) {
                return $instruction;
            }
        }

        return null;
    }
}
