<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

class CapturingMeilisearchEngine extends PagedMeilisearchEngine
{
    /** @var list<array{q: string, filter: string, sort: list<string>}> */
    public array $requests = [];

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->requests[] = ['q' => (string) $builder->query, 'filter' => (string) $this->filters($builder), 'sort' => array_values($this->buildSortFromOrderByClauses($builder))];

        return parent::paginate($builder, $perPage, $page);
    }
}
