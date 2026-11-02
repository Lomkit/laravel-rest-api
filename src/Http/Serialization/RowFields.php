<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class RowFields
{
    /**
     * @param  list<string>  $names
     * @param  list<string>  $aggregates
     */
    private function __construct(
        private FieldSet $fields,
        private array $names,
        private array $aggregates,
    ) {}

    public static function of(QueryPlan $plan): self
    {
        $fields = $plan->resource->getFields($plan->resource->context()->request);

        return new self(
            $fields,
            $plan->selects ?? $fields->selectable()->names(),
            array_map(static fn (AggregateClause $aggregate): string => $aggregate->key->value, $plan->aggregates),
        );
    }

    /** @return array<string, mixed> */
    public function values(Model $model): array
    {
        $attributes = $model->attributesToArray();
        $columns = array_diff_key($attributes, array_flip($this->aggregates));

        return array_intersect_key(
            array_replace($attributes, $this->fields->fromStorage($columns)),
            array_flip([...$this->names, ...$this->aggregates]),
        );
    }
}
