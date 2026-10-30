<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Console\Generation\Schema\Column;

final readonly class ModelColumns
{
    /** @param  list<string>  $relationKeys */
    public function __construct(private Model $model, private array $relationKeys) {}

    public function isKey(Column $column): bool
    {
        return $column->name === $this->model->getKeyName();
    }

    public function typeOf(Column $column): ?FieldType
    {
        return FieldType::fromCast($this->model->getCasts()[$column->name] ?? null) ?? FieldType::fromColumn($column);
    }

    public function isHidden(Column $column): bool
    {
        return in_array($column->name, $this->model->getHidden(), true);
    }

    public function isLeftOut(Column $column): bool
    {
        return ! $this->isKey($column)
            && $this->isHidden($column)
            && $this->declaresFillable()
            && ! $this->model->isFillable($column->name);
    }

    public function isManaged(Column $column): bool
    {
        return $column->autoIncrement || $column->generated || in_array($column->name, $this->managedColumns(), true);
    }

    /** @return list<string> */
    private function managedColumns(): array
    {
        $model = $this->model;

        return array_values(array_filter([
            $model->getIncrementing() ? $model->getKeyName() : null,
            ...($model->usesUniqueIds() ? $model->uniqueIds() : []),
            ...($model->usesTimestamps() ? [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()] : []),
            method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
            ...$this->relationKeys,
        ], is_string(...)));
    }

    private function declaresFillable(): bool
    {
        return $this->model->getFillable() !== [] || $this->model->getGuarded() !== ['*'];
    }
}
