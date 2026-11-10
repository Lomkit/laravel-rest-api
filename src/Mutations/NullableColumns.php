<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;

final class NullableColumns
{
    /** @var array<string, array<string, bool>> */
    private array $tables = [];

    public function allows(Model $model, string $column): bool
    {
        return $this->columns($model)[$column] ?? true;
    }

    public function exists(Model $model): bool
    {
        return $this->columns($model) !== [];
    }

    /** @return array<string, bool> */
    private function columns(Model $model): array
    {
        $table = $model->getConnection()->getName().'.'.$model->getTable();

        return $this->tables[$table] ??= self::read($model);
    }

    /** @return array<string, bool> */
    private static function read(Model $model): array
    {
        $columns = $model
            ->getConnection()
            ->getSchemaBuilder()
            ->getColumns($model->getTable());

        return array_column($columns, 'nullable', 'name');
    }
}
