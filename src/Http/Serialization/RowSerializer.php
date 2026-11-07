<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\Plan\Columns\StrictColumns;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class RowSerializer implements RowRenderer
{
    private function __construct(
        private RowFields $fields,
        private GateTable $gates,
        private RelationRenderer $relations,
        private StrictColumns $strict,
    ) {}

    public static function of(QueryPlan $plan, ?Relation $through = null, ?GateReach $reach = null): self
    {
        return new self(RowFields::of($plan), GateTable::of($plan, $reach ?? GateReach::everyRow()), RelationRenderer::of($plan, $through), StrictColumns::of($plan));
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function row(Model $model): array
    {
        $row = $this->strict->run(fn (): array => $this->gates->addTo($this->fields->values($model), $model));

        return array_merge($row, $this->relations->render($model));
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->gates->meta();
    }
}
