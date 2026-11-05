<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

enum KeyRead
{
    case Keys;
    case Rows;

    /**
     * @param  Builder<Model>  $query
     * @return list<array{mixed, Model|null}>
     */
    public function read(Builder $query): array
    {
        $model = $query->getModel();

        if ($this === self::Keys) {
            $keys = $query
                ->toBase()
                ->pluck($model->getQualifiedKeyName())
                ->all();

            return array_map(static fn (mixed $key): array => [$key, null], array_values($keys));
        }

        return array_map(static fn (Model $row): array => [$row->getRawOriginal($model->getKeyName()), $row], array_values($query->get()->all()));
    }
}
