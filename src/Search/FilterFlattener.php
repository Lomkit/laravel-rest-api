<?php

namespace Lomkit\Rest\Search;

use LogicException;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;

final readonly class FilterFlattener
{
    /** @return list<FlatFilter> */
    public function flatten(FilterGroup $group): array
    {
        return $this->group($group, '', true);
    }

    /** @return list<FlatFilter> */
    private function group(FilterGroup $group, string $prefix, bool $exists): array
    {
        $filters = [];

        foreach ($group->filters as $node) {
            array_push($filters, ...$this->node($node, $prefix, $exists));
        }

        return $filters;
    }

    /** @return list<FlatFilter> */
    private function types(MorphFilterGroup $node, string $prefix, bool $exists): array
    {
        $filters = [];

        foreach ($node->types as $group) {
            array_push($filters, ...$this->group($group, $prefix, $exists));
        }

        return $filters;
    }

    /** @return list<FlatFilter> */
    private function node(FilterNode $node, string $prefix, bool $exists): array
    {
        return match (true) {
            $node instanceof FilterGroup => $this->group($node, $prefix, $exists),
            $node instanceof RelationFilterGroup => $this->group($node->filters, $prefix.$node->relation->relation.'.', $exists && $node->exists),
            $node instanceof MorphFilterGroup => $this->types($node, $prefix.$node->relation->relation.'.', $exists && $node->exists),
            $node instanceof FilterCondition, $node instanceof EngineFilterCondition => [new FlatFilter($prefix.$node->name, $node->operator, $node->value, $exists)],
            default => throw new LogicException($node::class.' is not a filter of a search.'),
        };
    }
}
