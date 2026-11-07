<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

class FilterCapturingTypesenseEngine extends FakeTypesenseEngine
{
    /** @var list<string> */
    public array $filters = [];

    /**
     * @param  Builder<Model>  $builder
     * @return array{hits: list<never>, found: int}
     */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->filters[] = $this->buildSearchParameters($builder, (int) $page, (int) $perPage)['filter_by'];

        return ['hits' => [], 'found' => 0];
    }
}
