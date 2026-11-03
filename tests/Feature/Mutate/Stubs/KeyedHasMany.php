<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\Relation;

class KeyedHasMany extends HasMany
{
    #[\Override]
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $builder = app()->make(EloquentQueryBuilder::class, ['resource' => $relation->resource()]);

        foreach ($mutationRelations[$relation->relation] as $mutationRelation) {
            foreach ($mutationRelation['key'] as $key) {
                $builder->applyMutation($mutationRelation, ['category_id' => $model->getKey()], $key);
            }
        }
    }
}
