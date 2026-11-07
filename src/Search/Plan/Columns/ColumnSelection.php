<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\GateAbility;

enum ColumnSelection
{
    case Rendered;
    case Every;

    public static function of(Resource $resource): self
    {
        return $resource->context()->config()->selectColumns ? self::Rendered : self::Every;
    }

    /**
     * @param  list<string>|null  $selects
     * @param  list<string>  $includes
     * @param  list<string>  $keys
     * @param  list<GateAbility>  $gates
     */
    public function level(Resource $resource, ?array $selects, array $includes, array $keys, array $gates = []): ?ColumnSet
    {
        if ($this === self::Every) {
            return null;
        }

        $model = $resource::newModel();
        $fields = $resource->definition()->fields();
        $columns = [];

        foreach ($selects ?? $fields->selectable()->names() as $name) {
            $field = $fields->get($name);
            $columns = [...$columns, ...$field->isVirtual() ? [] : [$field->name], ...$field->getRequires()];
        }

        foreach ($includes as $include) {
            $keys = [...$keys, ...RelationKeys::between($model, $include)->parent()];
        }

        return ColumnSet::of($model, [
            ...$columns,
            $model->getKeyName(),
            ...$resource->alwaysSelect(),
            ...$keys,
            ...self::version($resource, $model, $gates),
        ]);
    }

    /**
     * @param  list<GateAbility>  $gates
     * @return list<string>
     */
    private static function version(Resource $resource, Model $model, array $gates): array
    {
        $column = $model->getUpdatedAtColumn();

        if ($column === null || ! $model->usesTimestamps() || ! $resource->isGatingEnabled() || ! $resource->context()->config()->cacheEnabled) {
            return [];
        }

        $abilities = $resource->definition()->cachedAbilities();

        foreach ($gates as $gate) {
            if (! $gate->isClassLevel() && $abilities->seconds($gate->name()) !== null) {
                return [$column];
            }
        }

        return [];
    }
}
