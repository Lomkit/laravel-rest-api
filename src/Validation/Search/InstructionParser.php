<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\InstructionNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class InstructionParser
{
    private const array KEYS = ['name' => true, 'alias' => true, 'fields' => true];

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
        $declared = $context->grammar->instructions($resource->definition()->instructions());
        $names = $context->vocabulary->of($resource, $context->grammar, 'instructions', fn (): array => array_values(array_map(
            fn (Instruction $instruction): string => $instruction->uriKey(),
            $declared,
        )));
        $keys = InstructionAlias::of($entries);
        $instructions = [];

        foreach ($entries as $entry) {
            $instruction = $this->instruction($entry, $declared, $names, $keys, $context);

            if ($instruction !== null) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    /** @param array<int, Instruction> $declared */
    private function instruction(Cursor $entry, array $declared, OneOf $names, InstructionAlias $keys, ValidationContext $context): ?InstructionNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, self::KEYS);

        $name = $entry->at('name');
        $alias = $entry->at('alias');
        $fields = $entry->at('fields');
        $instruction = self::find($declared, $name->value);

        $context->rules->gate($entry, $name);
        $context->rules->add($name->path, [$names, 'required', 'string']);
        $context->rules->addWhenPresent($alias, is_string($alias->value) ? ['string', 'max:255', 'regex:'.RowKeys::PATTERN, $keys] : ['string']);
        $context->rules->addWhenPresent($fields, ['array']);
        $context->reader->admit($fields, $context->limits->sizes->fields);

        if ($instruction === null) {
            $context->rules->partial();

            return null;
        }

        $inputs = new InputSet($context->resource->definition()->inputFields($instruction));
        $values = $inputs->named($fields, $context, 'fields:'.$instruction->uriKey());

        if (! $alias->present && $keys->isShared($instruction->uriKey())) {
            $context->rules->reject(Violation::takenKey($alias->path, $instruction->uriKey()));
        }

        $aliased = is_string($alias->value) ? $alias->value : null;

        if ($alias->present && $aliased === null) {
            $context->rules->partial();

            return null;
        }

        return new InstructionNode($instruction, $values, $aliased);
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
