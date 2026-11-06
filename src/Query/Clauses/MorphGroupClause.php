<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\MorphFilterGroup;

final readonly class MorphGroupClause implements Clause
{
    /** @param non-empty-array<string, FilterGroupClause> $types */
    public function __construct(
        private Relation&PolymorphicRelation $relation,
        private bool $exists,
        private array $types,
        private string $boolean = 'and',
    ) {}

    public static function of(MorphFilterGroup $group): self
    {
        return new self($group->relation, $group->exists, array_map(FilterGroupClause::of(...), $group->types), $group->boolean());
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        if ($this->exists) {
            $this->hasTarget($query, $this->boolean);

            return;
        }

        $query->where(function (Builder $query): void {
            $query
                ->whereNot(function (Builder $query): void {
                    $this->hasTarget($query, 'and');
                })
                ->orWhereMorphedTo($this->relation->relation, null);
        }, null, null, $this->boolean);
    }

    private function hasTarget(Builder $query, string $boolean): void
    {
        $models = [];

        foreach (array_keys($this->types) as $type) {
            $target = $this->relation->targets()->forType($type) ?? throw new LogicException($type.' is not a type of the '.$this->relation->relation.' relation.');
            $models[] = $target->resource::modelClass();
        }

        $query->hasMorph($this->relation->relation, $models, '>=', 1, $boolean, $this->constrain(...));
    }

    /** @param EloquentBuilder<Model> $query */
    private function constrain(EloquentBuilder $query): void
    {
        $target = $this->relation->targets()->forModel($query->getModel()) ?? throw new LogicException($query->getModel()::class.' is not a target of the '.$this->relation->relation.' relation.');

        $this->types[$target->type]->apply($query);

        $query->where(function (EloquentBuilder $query): void {
            $this->relation->applyPerimeter($query);
        });
    }
}
