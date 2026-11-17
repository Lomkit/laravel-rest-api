<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

class FilterCapturingTypesenseEngine extends FakeTypesenseEngine
{
    /** @var list<string> */
    public array $filters = [];

    /** @var list<array<string, mixed>> */
    public array $parameters = [];

    /**
     * @param  Builder<Model>  $builder
     * @return array{hits: list<never>, found: int}
     */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $parameters = $this->buildSearchParameters($builder, (int) $page, (int) $perPage);
        $this->filters[] = $parameters['filter_by'];
        $this->parameters[] = $parameters;

        return ['hits' => [], 'found' => 0];
    }
}
