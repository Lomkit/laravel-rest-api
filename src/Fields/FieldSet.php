<?php

namespace Lomkit\Rest\Fields;

use ArrayIterator;
use Closure;
use Countable;
use Illuminate\Database\Eloquent\Model;
use IteratorAggregate;
use Lomkit\Rest\Exceptions\InvalidFieldException;

/** @implements IteratorAggregate<int, Field> */
final readonly class FieldSet implements Countable, IteratorAggregate
{
    /** @param list<Field> $fields */
    private function __construct(private array $fields) {}

    /** @param array<mixed> $fields */
    public static function of(array $fields, string $owner = 'fields()'): self
    {
        $declared = [];

        foreach (array_values($fields) as $index => $field) {
            if (! $field instanceof Field) {
                throw InvalidFieldException::notAField($owner, $index, $field);
            }

            if (isset($declared[$field->name])) {
                throw InvalidFieldException::duplicateName($owner, $field->name);
            }

            $declared[$field->name] = $field;
        }

        return new self(array_values($declared));
    }

    /** @param array<mixed> $fields */
    public static function forInputs(array $fields, string $owner): self
    {
        $set = self::of($fields, $owner);

        foreach ($set->fields as $field) {
            if ($field->getRules()->hasWriteRules()) {
                throw InvalidFieldException::writeRulesOnInput($owner, $field->name);
            }

            if (is_numeric($field->name)) {
                throw InvalidFieldException::numericInputName($owner, $field->name);
            }
        }

        return $set;
    }

    public function bindTo(Model $model): self
    {
        foreach ($this->fields as $field) {
            $field->bind($model);
        }

        return $this;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map(fn (Field $field): string => $field->name, $this->fields);
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    public function find(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function get(string $name): Field
    {
        return $this->find($name) ?? throw InvalidFieldException::unknownField($name);
    }

    public function selectable(): self
    {
        return $this->where(fn (Field $field): bool => $field->isSelectable());
    }

    public function sortable(): self
    {
        return $this->where(fn (Field $field): bool => $field->isSortable());
    }

    public function filterable(): self
    {
        return $this->where(fn (Field $field): bool => $field->isFilterable());
    }

    public function writableOn(WriteOperation $operation): self
    {
        return $this->where(fn (Field $field): bool => $field->isWritableOn($operation));
    }

    /** @return array<string, list<mixed>> */
    public function rulesFor(WriteOperation $operation): array
    {
        $rules = [];

        foreach ($this->writableOn($operation) as $field) {
            $rules[$field->name] = $field->getRules()->forWrite($operation);
        }

        return $rules;
    }

    /** @return ArrayIterator<int, Field> */
    #[\Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->fields);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->fields);
    }

    /** @param Closure(Field): bool $keep */
    private function where(Closure $keep): self
    {
        return new self(array_values(array_filter($this->fields, $keep)));
    }
}
