<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use ArrayAccess;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Throwable;

/** @internal */
final readonly class TypesenseFacetSchema
{
    private const array NUMBERS = ['int32', 'int64', 'float'];

    public function __construct(private EngineTotals $totals) {}

    public function ensure(TypesenseEngine $engine, string $collection): void
    {
        $fields = $this->fields($engine, $collection);

        foreach ($this->totals->node->groups as $group) {
            $type = $this->facet($fields, $group->field->name);

            if (str_ends_with($type, '[]')) {
                throw EngineSearchException::multiValuedFacet($this->totals->resource, $group->field->name);
            }
        }

        foreach ($this->totals->node->measuredNames() as $name) {
            $type = $this->facet($fields, $name);

            if (! in_array($type, self::NUMBERS, true)) {
                throw EngineSearchException::unmeasurableFacet($this->totals->resource, $name, $type);
            }
        }
    }

    /** @param list<array<array-key, mixed>> $fields */
    private function facet(array $fields, string $attribute): string
    {
        foreach ($fields as $field) {
            $name = is_string($field['name'] ?? null) ? $field['name'] : '';
            $matches = $name === $attribute || (preg_match('/[.*+?^$\[\](){}|\\\\]/', $name) === 1 && preg_match('/^(?:'.str_replace('/', '\/', $name).')$/', $attribute) === 1);

            if ($matches && ($field['facet'] ?? false) === true && is_string($field['type'] ?? null)) {
                return $field['type'];
            }
        }

        throw EngineSearchException::missingFacet($this->totals->resource, $attribute, $this->totals->engine);
    }

    /** @return list<array<array-key, mixed>> */
    private function fields(TypesenseEngine $engine, string $collection): array
    {
        try {
            $collections = $engine->__call('getCollections', []);
            $schema = $collections instanceof ArrayAccess ? $collections[$collection] : null;
            $schema = is_object($schema) && method_exists($schema, 'retrieve') ? $schema->retrieve() : null;
        } catch (Throwable $error) {
            throw EngineSearchException::unreadSchema($this->totals->resource, $this->totals->engine, $error);
        }

        $fields = is_array($schema) && is_array($schema['fields'] ?? null) ? $schema['fields'] : null;

        if ($fields === null) {
            throw EngineSearchException::unreadSchema($this->totals->resource, $this->totals->engine);
        }

        return array_values(array_filter($fields, is_array(...)));
    }
}
