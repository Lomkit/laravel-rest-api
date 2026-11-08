<?php

namespace Lomkit\Rest\Fields\Details;

use Closure;
use JsonSerializable;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\WriteOperation;
use stdClass;

final readonly class FieldDetails implements JsonSerializable
{
    public const array PARTS = ['capabilities', 'filter', 'rules', 'meta'];

    /** @param list<string> $parts */
    private function __construct(
        private Field $field,
        private array $parts = self::PARTS,
        private bool $sortable = true,
        private bool $queryable = true,
    ) {}

    /** @param list<string> $parts */
    public static function of(Field $field, array $parts = self::PARTS): self
    {
        return new self($field, $parts);
    }

    public static function ofPivot(Field $field, bool $queryable): self
    {
        return new self($field, sortable: false, queryable: $queryable);
    }

    /**
     * @param  list<string>  $parts
     * @return list<self>
     */
    public static function forFields(FieldSet $fields, array $parts = self::PARTS): array
    {
        return array_map(fn (Field $field): self => self::of($field, $parts), iterator_to_array($fields, false));
    }

    /** @return list<self> */
    public static function forPivotFields(FieldSet $fields, bool $queryable): array
    {
        return array_map(fn (Field $field): self => self::ofPivot($field, $queryable), iterator_to_array($fields, false));
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        $details = [
            'name' => $this->field->name,
            'label' => $this->field->getLabel(),
            'type' => $this->field->type(),
            'nullable' => $this->field->isNullable(),
        ];

        foreach ($this->parts() as $key => [$part, $value]) {
            if (in_array($part, $this->parts, true)) {
                $details[$key] = $value();
            }
        }

        if (! $this->field instanceof Enum) {
            return $details;
        }

        return [...$details, ...(new EnumCases($this->field))->toArray()];
    }

    /** @return array<string, array{string, Closure(): mixed}> */
    private function parts(): array
    {
        return [
            'select' => ['capabilities', fn (): bool => $this->queryable && $this->field->isSelectable()],
            'sort' => ['capabilities', fn (): bool => $this->sortable && $this->field->isSortable()],
            'filter' => ['filter', fn (): array => ['operators' => $this->operators()]],
            'create' => ['capabilities', fn (): bool => $this->field->isWritableOn(WriteOperation::Create)],
            'update' => ['capabilities', fn (): bool => $this->field->isWritableOn(WriteOperation::Update)],
            'rules' => ['rules', $this->rules(...)],
            'meta' => ['meta', fn (): stdClass => (object) $this->field->getMeta()],
        ];
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
