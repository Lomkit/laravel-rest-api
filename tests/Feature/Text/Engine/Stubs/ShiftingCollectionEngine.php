<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\CollectionEngine;

class ShiftingCollectionEngine extends CollectionEngine
{
    private ?Model $first = null;

    public function __construct(private readonly bool $repeats)
    {
        parent::__construct();
    }

    /** @param Builder<Model> $builder */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = parent::paginate($builder, $perPage, $page);
        $models = array_values($result['results']);

        if ((int) $page === 1) {
            $this->first = $models[0] ?? null;
        }

        if ((int) $page !== 2) {
            return $result;
        }

        $shifted = array_slice($models, 1);

        return ['results' => $this->repeats ? [$this->first, ...$shifted] : $shifted, 'total' => $result['total']];
    }
}
