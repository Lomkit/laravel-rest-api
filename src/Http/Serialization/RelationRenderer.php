<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\Plan\AliasedIncludeClause;
use Lomkit\Rest\Search\Plan\IncludeClause;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class RelationRenderer
{
    /** @param array<string, array{string, RowRenderer}> $planned */
    private function __construct(
        private Resource $resource,
        private PivotRenderer $pivot,
        private array $planned,
    ) {}

    public static function of(QueryPlan $plan, ?Relation $through): self
    {
        $planned = [];

        foreach ($plan->includes as $include) {
            [$name, $key, $rows] = match (true) {
                $include instanceof AliasedIncludeClause => [$include->alias, $include->alias, RowSerializer::of($include->plan, $plan->resource->relation($include->relation))],
                $include instanceof IncludeClause => [$include->relation, Str::snake($include->relation), RowSerializer::of($include->plan, $plan->resource->relation($include->relation))],
                default => throw new LogicException($include::class.' is not an include of a plan.'),
            };

            $planned[$name] = [$key, $rows];
        }

        return new self($plan->resource, PivotRenderer::of($through), $planned);
    }

    /** @return array<string, mixed> */
    public function render(Model $model): array
    {
        $rendered = [];

        foreach ($model->getRelations() as $name => $value) {
            [$key, $rows] = $this->planned[$name] ?? [Str::snake($name), null];
            $rendered[$key] = $this->value($model, $name, $value, $rows);
        }

        return $rendered;
    }

    private function value(Model $model, string $name, mixed $value, ?RowRenderer $rows): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Pivot) {
            return $this->pivot->render($value);
        }

        $rows ??= $this->unplanned($model, $name);

        if ($value instanceof Model) {
            return $rows->row($value);
        }

        if ($value instanceof Collection) {
            return array_map($rows->row(...), $value->ensure(Model::class)->all());
        }

        throw new LogicException('The '.$name.' relation of '.$model::class.' holds neither a model nor a collection.');
    }

    private function unplanned(Model $model, string $name): RowSerializer
    {
        $relation = $this->resource->relation($name)
            ?? throw new LogicException('The '.$name.' relation of '.$model::class.' is not declared by '.$this->resource::class.'.');

        return RowSerializer::of(QueryPlan::bare($relation->resource()), $relation);
    }
}
