<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\CollectionEngine;

class PagedAlgoliaEngine extends FakeAlgolia4Engine
{
    /** @var list<array{int, int}> */
    public array $pages = [];

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->pages[] = [(int) $perPage, (int) $page];
        $result = (new CollectionEngine)->paginate($builder, $perPage, $page);

        return [
            'hits' => array_map(fn (Model $model): array => ['objectID' => $model->getScoutKey()], array_values($result['results'])),
            'nbHits' => $result['total'],
        ];
    }

    #[\Override]
    public function update($models): void {}

    #[\Override]
    public function delete($models): void {}
}
