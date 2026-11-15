<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
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
        $definition = $resource->definition();
        $fields = $definition->fields();
        $hidden = $definition->fieldNarrowing()->hiddenFrom($definition->declaredFields())->selectable();
        $columns = [];

        foreach ($selects ?? $fields->selectable()->names() as $name) {
            $columns = [...$columns, ...self::columnsOf($fields->get($name))];
        }

        foreach ($hidden as $field) {
            $columns = [...$columns, ...self::columnsOf($field)];
        }

        foreach ($includes as $include) {
            $keys = [...$keys, ...RelationKeys::between($model, $include)->parent()];
        }

        return ColumnSet::of($model, [
            ...$columns,
            $model->getKeyName(),
            ...$definition->alwaysSelect(),
            ...$keys,
            ...self::version($resource, $model, $gates),
        ]);
    }

    /** @return list<string> */
    private static function columnsOf(Field $field): array
    {
        return [...$field->isVirtual() ? [] : [$field->name], ...$field->getRequires()];
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
