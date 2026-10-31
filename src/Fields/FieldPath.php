<?php

namespace Lomkit\Rest\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;

final readonly class FieldPath
{
    /** @param list<Relation> $relations */
    private function __construct(
        public array $relations,
        public Field $field,
        public Resource $resource,
        public ?string $pivotTable = null,
    ) {}

    public static function resolve(Resource $resource, string $path): ?self
    {
        $segments = explode('.', $path);
        $name = array_pop($segments);
        $pivot = $segments !== [] && $segments[array_key_last($segments)] === 'pivot';

        if ($pivot) {
            array_pop($segments);
        }

        $relations = self::walk($resource, $segments);

        if (count($relations) !== count($segments)) {
            return null;
        }

        return $pivot ? self::pivot($resource, $relations, $name) : self::field($resource, $relations, $name);
    }

    /** @return list<Relation> */
    public static function relationsAlong(Resource $resource, string $path): array
    {
        $segments = explode('.', $path);
        array_pop($segments);

        return self::walk($resource, $segments);
    }

    public function isPivot(): bool
    {
        return $this->pivotTable !== null;
    }

    public function column(Model $model): ?string
    {
        if ($this->pivotTable !== null) {
            return $this->pivotTable.'.'.$this->field->name;
        }

        return $this->field->isVirtual() ? null : $model->qualifyColumn($this->field->name);
    }

    /**
     * @param  list<string>  $segments
     * @return list<Relation>
     */
    private static function walk(Resource $resource, array $segments): array
    {
        $relations = [];

        foreach ($segments as $segment) {
            $relation = $segment === '' || $segment === 'pivot' ? null : $resource->relation($segment);

            if ($relation === null || ($relation instanceof MorphTo && $relation->targets()->isPolymorphic())) {
                break;
            }

            $relations[] = $relation;
            $resource = $relation->resource();
        }

        return $relations;
    }

    /** @param list<Relation> $relations */
    private static function field(Resource $resource, array $relations, string $name): ?self
    {
        $resource = $relations === [] ? $resource : $relations[array_key_last($relations)]->resource();
        $field = $resource->getFields($resource->context()->request)->find($name);

        if ($field === null || ! $field->isFilterable()) {
            return null;
        }

        return new self($relations, $field, $resource);
    }

    /** @param list<Relation> $relations */
    private static function pivot(Resource $resource, array $relations, string $name): ?self
    {
        $count = count($relations);
        $relation = $count === 0 ? null : $relations[$count - 1];

        if (! $relation instanceof PivotRelation) {
            return null;
        }

        $owner = $count === 1 ? $resource : $relations[$count - 2]->resource();
        $field = $relation->getPivotFields()->find($name);
        $eloquent = $owner::newModel()->{$relation->relation}();

        if ($field === null || ! $field->isFilterable() || ! $eloquent instanceof EloquentBelongsToMany) {
            return null;
        }

        return new self($relations, $field, $relation->resource(), $eloquent->getTable());
    }
}
