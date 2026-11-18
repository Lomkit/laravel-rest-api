<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

class FilterCapturingAlgolia4Engine extends FakeAlgolia4Engine
{
    /** @var list<string> */
    public array $filters = [];

    /**
     * @param  Builder<Model>  $builder
     * @return array{hits: list<never>, nbHits: int}
     */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $filters = $this->filters($builder);
        $this->filters[] = is_string($filters) ? $filters : '';

        return ['hits' => [], 'nbHits' => 0];
    }
}
