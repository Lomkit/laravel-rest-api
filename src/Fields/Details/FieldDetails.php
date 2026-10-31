<?php

namespace Lomkit\Rest\Fields\Details;

use JsonSerializable;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\WriteOperation;
use stdClass;

final readonly class FieldDetails implements JsonSerializable
{
    private function __construct(
        private Field $field,
        private bool $sortable = true,
        private bool $queryable = true,
    ) {}

    public static function of(Field $field): self
    {
        return new self($field);
    }

    public static function ofPivot(Field $field, bool $queryable): self
    {
        return new self($field, sortable: false, queryable: $queryable);
    }

    /** @return list<self> */
    public static function forFields(FieldSet $fields): array
    {
        return array_map(fn (Field $field): self => self::of($field), iterator_to_array($fields, false));
    }

    /** @return list<self> */
    public static function forPivotFields(FieldSet $fields, bool $queryable): array
    {
        return array_map(fn (Field $field): self => self::ofPivot($field, $queryable), iterator_to_array($fields, false));
    }

    /** @return array{name: string, type: string, nullable: bool, select: bool, sort: bool, filter: array{operators: list<string>}, create: bool, update: bool, rules: array{all: list<string>, create: list<string>, update: list<string>}, meta: stdClass, values?: list<int|string>} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $details = [
            'name' => $this->field->name,
            'type' => $this->field->type(),
            'nullable' => $this->field->isNullable(),
            'select' => $this->queryable && $this->field->isSelectable(),
            'sort' => $this->sortable && $this->field->isSortable(),
            'filter' => ['operators' => $this->operators()],
            'create' => $this->field->isWritableOn(WriteOperation::Create),
            'update' => $this->field->isWritableOn(WriteOperation::Update),
            'rules' => $this->rules(),
            'meta' => (object) $this->field->getMeta(),
        ];

        if (! $this->field instanceof Enum) {
            return $details;
        }

        return [...$details, 'values' => $this->field->values()];
    }

    /** @return list<string> */
    private function operators(): array
    {
        if (! $this->queryable) {
            return [];
        }

        return array_map(fn (FilterOperator $operator): string => $operator->value, $this->field->filterOperators());
    }

    /** @return array{all: list<string>, create: list<string>, update: list<string>} */
    private function rules(): array
    {
        $rules = $this->field->getRules();

        return [
            'all' => (new RuleDescriptions($rules->shared()))->toArray(),
            'create' => (new RuleDescriptions($rules->declaredFor(WriteOperation::Create)))->toArray(),
            'update' => (new RuleDescriptions($rules->declaredFor(WriteOperation::Update)))->toArray(),
        ];
    }
}
