<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Concerns\ConfiguresFiltering;
use Lomkit\Rest\Fields\Concerns\ConfiguresReading;
use Lomkit\Rest\Fields\Concerns\ConfiguresRules;
use Lomkit\Rest\Fields\Concerns\ConfiguresWriting;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

abstract class Field
{
    use ConfiguresFiltering;
    use ConfiguresReading;
    use ConfiguresRules;
    use ConfiguresWriting;
    use HasMeta;

    final public function __construct(public readonly string $name)
    {
        if (trim($name) === '') {
            throw InvalidFieldException::emptyName();
        }

        if (str_contains($name, '.')) {
            throw InvalidFieldException::dottedName($name);
        }
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /** @return list<string> */
    abstract public function operators(): array;

    /** @return list<mixed> */
    abstract public function baseRules(): array;

    public function type(): string
    {
        return Str::snake(class_basename(static::class));
    }

    public function bind(Model $model): void {}

    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->castValue($value);
    }

    public function applyFilter(Builder $query, FilterOperator|string $operator, mixed $value, string $boolean = 'and', ?string $column = null): void
    {
        $operator = FilterOperator::parse($operator);

        if (! in_array($operator, $this->supportedOperators(), true)) {
            throw InvalidFieldException::unsupportedOperator($this->name, $operator->value);
        }

        $operator->condition()->apply($query, $column ?? $query->qualifyColumn($this->name), $this->filterValue($query, $value), $boolean);
    }

    /** @return list<FilterOperator> */
    public function supportedOperators(): array
    {
        $operators = array_map(fn (string $operator): FilterOperator => FilterOperator::parse($operator), $this->operators());

        if ($this->isNullable()) {
            array_push($operators, FilterOperator::IsNull, FilterOperator::IsNotNull);
        }

        return array_values(array_unique($operators, SORT_REGULAR));
    }

    protected function castValue(mixed $value): mixed
    {
        return $value;
    }

    protected function filterValue(Builder $query, mixed $value): mixed
    {
        return $value;
    }

    protected function sortableByDefault(): bool
    {
        return true;
    }

    protected function filterableByDefault(): bool
    {
        return true;
    }

    protected function writableByDefault(): bool
    {
        return true;
    }
}
