<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Lomkit\Rest\Http\Resource;

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
     */
    public function level(Resource $resource, ?array $selects, array $includes, array $keys): ?ColumnSet
    {
        if ($this === self::Every) {
            return null;
        }

        $model = $resource::newModel();
        $fields = $resource->getFields();
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
        ]);
    }
}
