<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use ArrayAccess;
use RuntimeException;

/** @implements ArrayAccess<string, self> */
class TypesenseSchemas implements ArrayAccess
{
    public const array FIELDS = [
        ['name' => 'code', 'type' => 'string', 'facet' => false],
        ['name' => 'number', 'type' => 'int32', 'facet' => true],
        ['name' => 'team', 'type' => 'string', 'facet' => true],
        ['name' => 'price', 'type' => 'float', 'facet' => true],
        ['name' => 'active', 'type' => 'bool', 'facet' => true],
    ];

    /** @var list<string> */
    public array $read = [];

    private string $collection = '';

    /** @param list<array<string, mixed>>|null $fields */
    public function __construct(public ?array $fields = self::FIELDS) {}

    /** @return array<string, mixed> */
    public function retrieve(): array
    {
        $this->read[] = $this->collection;

        return $this->fields === null ? throw new RuntimeException('Forbidden: the API key cannot read collections.') : ['name' => $this->collection, 'fields' => $this->fields];
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    #[\Override]
    public function offsetGet(mixed $offset): self
    {
        $this->collection = (string) $offset;

        return $this;
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {}

    #[\Override]
    public function offsetUnset(mixed $offset): void {}
}
