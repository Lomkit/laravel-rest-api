<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\MorphToIncludeClause;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class MorphToSerializer implements RowRenderer
{
    /** @param array<string, RowSerializer> $branches */
    private function __construct(
        private string $typeKey,
        private array $branches,
    ) {}

    public static function of(MorphToIncludeClause $include, Resource $resource): self
    {
        $relation = $resource->relation($include->relation);

        return new self(
            $resource->context()->config()->morphTypeKey,
            array_map(static fn (QueryPlan $plan): RowSerializer => RowSerializer::of($plan, $relation), $include->branches),
        );
    }

    #[\Override]
    public function row(Model $model): array
    {
        $type = $model->getMorphClass();
        $rows = $this->branches[$type] ?? throw new LogicException('The type '.$type.' is not a target of the include.');

        return [$this->typeKey => $type] + $rows->row($model);
    }
}
