<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\CollectionEngine;

class PagedTurbopufferEngine extends FakeTurbopufferEngine
{
    /** @var list<array{int, int}> */
    public array $pages = [];

    public function __construct(private readonly bool $endless = false) {}

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->pages[] = [(int) $perPage, (int) $page];

        if ($this->endless) {
            return ['rows' => array_map(fn (int $row): array => ['id' => "X-{$page}-{$row}"], range(1, (int) $perPage)), 'total' => 99];
        }

        $result = (new CollectionEngine)->paginate($builder, $perPage, $page);

        return ['rows' => array_map(fn (Model $model): array => ['id' => $model->getScoutKey()], array_values($result['results'])), 'total' => 99];
    }

    #[\Override]
    public function update($models): void {}

    #[\Override]
    public function delete($models): void {}
}
