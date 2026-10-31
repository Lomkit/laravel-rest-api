<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\AppliesToEngine;

final readonly class EngineInstruction
{
    /** @param list<array{name: string, value?: mixed}> $fields */
    public function __construct(
        private Resource $resource,
        private string $name,
        private array $fields = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $search
     * @return list<self>
     */
    public static function fromSearch(Resource $resource, array $search): array
    {
        $instructions = is_array($search['instructions'] ?? null) ? $search['instructions'] : [];
        $clauses = [];

        foreach ($instructions as $instruction) {
            if (is_array($instruction) && is_string($instruction['name'] ?? null)) {
                $clauses[] = new self($resource, $instruction['name'], self::fields($instruction['fields'] ?? null));
            }
        }

        return $clauses;
    }

    public function apply(EngineQuery $query): void
    {
        $request = $this->resource->context()->request;
        $instruction = $this->resource->instruction($request, $this->name)
            ?? throw InvalidInstructionException::undeclared($this->resource::class, $this->name);

        if (! $instruction instanceof AppliesToEngine) {
            throw InvalidInstructionException::queryOnly($instruction::class);
        }

        $instruction->handleEngine(InputValues::fromNamed($instruction->getFields($request), $this->fields)->toArray(), $query);
    }

    /** @return list<array{name: string, value?: mixed}> */
    private static function fields(mixed $fields): array
    {
        $entries = [];

        foreach (is_array($fields) ? $fields : [] as $field) {
            if (is_array($field) && is_string($field['name'] ?? null)) {
                $entries[] = array_key_exists('value', $field) ? ['name' => $field['name'], 'value' => $field['value']] : ['name' => $field['name']];
            }
        }

        return $entries;
    }
}
