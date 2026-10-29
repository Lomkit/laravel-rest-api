<?php

namespace Lomkit\Rest\Scopes;

use JsonSerializable;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;

final class Scope implements JsonSerializable
{
    use HasMeta;

    private FieldSet $fields;

    private function __construct(public readonly string $name)
    {
        $this->fields = FieldSet::of([]);
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** @param list<Field> $fields */
    public function fields(array $fields): self
    {
        $this->fields = FieldSet::forInputs($fields, "Scope::make('{$this->name}')->fields()");

        return $this;
    }

    public function getFields(): FieldSet
    {
        return $this->fields;
    }

    /** @return array{name: string, fields: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'fields' => InputFieldDetails::forFields($this->fields),
            'meta' => $this->getMeta(),
        ];
    }
}
