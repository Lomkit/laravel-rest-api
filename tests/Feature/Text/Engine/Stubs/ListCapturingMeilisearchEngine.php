<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

class ListCapturingMeilisearchEngine extends FakeMeilisearchEngine
{
    /** @var list<string> */
    public array $filters = [];

    /**
     * @param  Builder<Model>  $builder
     * @return array{hits: list<never>, totalHits: int}
     */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $filter = array_merge($builder->options, array_filter(['filter' => $this->filters($builder)]))['filter'] ?? '';
        $this->filters[] = is_string($filter) ? $filter : '';

        return ['hits' => [], 'totalHits' => 0];
    }
}
