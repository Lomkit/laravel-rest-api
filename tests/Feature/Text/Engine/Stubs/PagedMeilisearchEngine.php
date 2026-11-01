<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\CollectionEngine;

class PagedMeilisearchEngine extends FakeMeilisearchEngine
{
    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = (new CollectionEngine)->paginate($builder, $perPage, $page);

        return [
            'hits' => array_map(fn (Model $model): array => [$model->getScoutKeyName() => $model->getScoutKey()], array_values($result['results'])),
            'totalHits' => $result['total'],
        ];
    }

    #[\Override]
    public function update($models): void {}

    #[\Override]
    public function delete($models): void {}
}
