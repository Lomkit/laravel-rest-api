<?php

namespace Lomkit\Rest\Fields\Details;

use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;

final readonly class InputFieldDetails
{
    public function __construct(private Field $field) {}

    /** @return list<array<string, mixed>> */
    public static function forFields(FieldSet $fields): array
    {
        return array_map(fn (Field $field): array => (new self($field))->toArray(), iterator_to_array($fields, false));
    }

    /** @return array{name: string, type: string, nullable: bool, default: mixed, rules: list<string>, meta: array<string, mixed>, values?: list<int|string>} */
    public function toArray(): array
    {
        $details = [
            'name' => $this->field->name,
            'type' => $this->field->type(),
            'nullable' => $this->field->isNullable(),
            'default' => $this->field->defaultValue(),
            'rules' => (new RuleDescriptions($this->field->getRules()->shared()))->toArray(),
            'meta' => $this->field->getMeta(),
        ];

        if (! $this->field instanceof Enum) {
            return $details;
        }

        return [...$details, 'values' => $this->field->values()];
    }
}
