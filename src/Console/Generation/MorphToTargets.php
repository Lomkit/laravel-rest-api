<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class MorphToTargets
{
    /** @param  MorphTo<Model, Model>  $relation */
    public function __construct(private MorphTo $relation) {}

    /**
     * @param  array<string, string>  $resources
     * @return list<string>
     */
    public function resources(array $resources): array
    {
        $mapped = array_intersect_key($resources, array_flip(Relation::morphMap()));

        return array_values(array_filter($mapped, $this->pointsBack(...), ARRAY_FILTER_USE_KEY));
    }

    private function pointsBack(string $model): bool
    {
        if (! is_a($model, Model::class, true)) {
            return false;
        }

        foreach (ModelRelations::of(new $model) as $relation) {
            if ($this->isInverse($relation)) {
                return true;
            }
        }

        return false;
    }

    /** @param  Relation<Model, Model, mixed>  $relation */
    private function isInverse(Relation $relation): bool
    {
        $child = $this->relation->getParent();
        $type = $this->relation->getMorphType();

        if ($relation instanceof MorphOneOrMany) {
            return $relation->getMorphType() === $type && $relation->getRelated()::class === $child::class;
        }

        if (! $relation instanceof MorphToMany || $relation->getInverse()) {
            return false;
        }

        return $relation->getMorphType() === $type && $relation->getTable() === $child->getTable();
    }
}
