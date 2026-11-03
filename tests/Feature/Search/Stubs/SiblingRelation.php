<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
 */
class SiblingRelation extends Relation
{
    #[\Override]
    public function addConstraints(): void {}

    #[\Override]
    public function addEagerConstraints(array $models): void {}

    #[\Override]
    public function initRelation(array $models, $relation): array
    {
        return $models;
    }

    #[\Override]
    public function match(array $models, Collection $results, $relation): array
    {
        return $models;
    }

    #[\Override]
    public function getResults(): Collection
    {
        return $this->query->get();
    }
}
