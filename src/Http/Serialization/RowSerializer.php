<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class RowSerializer implements RowRenderer
{
    private function __construct(
        private RowFields $fields,
        private GateTable $gates,
        private RelationRenderer $relations,
    ) {}

    public static function of(QueryPlan $plan, ?Relation $through = null): self
    {
        return new self(RowFields::of($plan), GateTable::of($plan), RelationRenderer::of($plan, $through));
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function row(Model $model): array
    {
        return array_merge($this->gates->addTo($this->fields->values($model), $model), $this->relations->render($model));
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->gates->meta();
    }
}
