<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ForeignEngine extends Engine
{
    #[\Override]
    public function update($models): void {}

    #[\Override]
    public function delete($models): void {}

    #[\Override]
    public function search(Builder $builder): array
    {
        return [];
    }

    #[\Override]
    public function paginate(Builder $builder, $perPage, $page): array
    {
        return [];
    }

    #[\Override]
    public function mapIds($results): BaseCollection
    {
        return new BaseCollection;
    }

    #[\Override]
    public function map(Builder $builder, $results, $model): Collection
    {
        return new Collection;
    }

    #[\Override]
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return new LazyCollection;
    }

    #[\Override]
    public function getTotalCount($results): int
    {
        return 0;
    }

    #[\Override]
    public function flush($model): void {}

    #[\Override]
    public function createIndex($name, array $options = []): void {}

    #[\Override]
    public function deleteIndex($name): void {}
}
