<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;

final class NullableColumns
{
    /** @var array<string, array<string, bool>> */
    private array $tables = [];

    public function allows(Model $model, string $column): bool
    {
        $table = $model->getConnection()->getName().'.'.$model->getTable();
        $this->tables[$table] ??= self::read($model);

        return $this->tables[$table][$column] ?? true;
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
