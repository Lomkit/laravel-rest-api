<?php

namespace Lomkit\Rest\Query\Operators;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;

class Filter implements Operator
{

    public function __construct(
        protected string $field,
        protected string $operator,
        protected mixed $value,
        protected string $type = 'and',
        protected ?array $nested = null
    )
    {}

    public function handle(Builder $query, Resource $resource): Builder
    {
        if ($this->nested !== null) {
            return $this->handleNestedFilter($query, $resource);
        }

        if ($this->isRelationFilter()) {
            return $this->handleRelationFilter($query, $resource);
        }

        return $this->applyWhere($query, $resource);
    }

    protected function handleNestedFilter(Builder $query, Resource $resource): Builder
    {
        return $query->where(function ($query) use ($resource) {
            (new Filter(
                field: $this->nested['field'] ?? '',
                operator: $this->nested['operator'] ?? '=',
                value: $this->nested['value'] ?? null,
                type: $this->nested['type'] ?? 'and',
                nested: $this->nested['nested'] ?? null
            ))
                ->handle($query, $resource);
        }, null, null, $this->type);
    }

    protected function isRelationFilter(): bool
    {
        return Str::contains($this->field, '.');
    }

    protected function handleRelationFilter(Builder $query, Resource $resource): Builder
    {
        $relation = $resource->relation(
            Str::beforeLast($this->field, '.')
        );

        return $relation->filter($query, $this->field, $this->operator, $this->value, $this->type, function ($query) use ($relation) {
            $relation->applySearchQuery($query);
        });
    }

    protected function applyWhere(Builder $query, Resource $resource): Builder
    {
        if (in_array($this->operator, ['in', 'not in'])) {
            return $query->whereIn($query->getModel()->getTable().'.'.$this->field, $this->value, $this->type, $this->operator === 'not in');
        }

        return $query->where($query->getModel()->getTable().'.'.$this->field, $this->operator, $this->value, $this->type);
    }
}