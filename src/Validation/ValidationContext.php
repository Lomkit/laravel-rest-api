<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Validation\Search\SearchGrammar;

final readonly class ValidationContext
{
    /**
     * @param  list<AggregateNode>  $aggregates
     * @param  list<string>  $reserved
     */
    public function __construct(
        public Resource $resource,
        public SearchGrammar $grammar,
        public Limits $limits,
        public ParsedRules $rules,
        public PayloadReader $reader,
        public Vocabulary $vocabulary,
        public int $filterDepth = 0,
        public int $includeDepth = 0,
        public array $aggregates = [],
        public array $reserved = [],
    ) {}

    public static function root(Resource $resource, NodeBudget $budget, ParsedRules $rules): self
    {
        $context = $resource->context();

        return new self($resource, $context->text($resource)->grammar(), $context->limits(), $rules, new PayloadReader($rules, $budget), $context->vocabulary());
    }

    public function for(Resource $resource): self
    {
        return $this->copy($resource, $this->grammar, $this->filterDepth, $this->includeDepth);
    }

    public function deeper(): self
    {
        return $this->copy($this->resource, $this->grammar, $this->filterDepth + 1, $this->includeDepth);
    }

    public function related(Resource $resource): self
    {
        return $this->copy($resource, $this->grammar->nested(), 0, $this->includeDepth);
    }

    public function included(int $levels): self
    {
        return $this->copy($this->resource, $this->grammar, $this->filterDepth, $this->includeDepth + $levels);
    }

    /** @param list<AggregateNode> $aggregates */
    public function withAggregates(array $aggregates): self
    {
        return new self($this->resource, $this->grammar, $this->limits, $this->rules, $this->reader, $this->vocabulary, $this->filterDepth, $this->includeDepth, $aggregates, $this->reserved);
    }

    /** @param list<string> $keys */
    public function reserving(array $keys): self
    {
        return new self($this->resource, $this->grammar, $this->limits, $this->rules, $this->reader, $this->vocabulary, $this->filterDepth, $this->includeDepth, $this->aggregates, $keys);
    }

    /** @return non-empty-list<Relation>|null */
    public function reachRelation(string $relation, string $path): ?array
    {
        $resource = $this->resource;
        $relations = [];

        foreach (explode('.', $relation) as $segment) {
            $declared = $resource->definition()->relation($segment);

            if ($declared === null) {
                return null;
            }

            $relations[] = $declared;

            if ($declared instanceof PolymorphicRelation && $declared->targets()->isPolymorphic()) {
                return count($relations) === count(explode('.', $relation)) ? $relations : null;
            }

            $resource = $declared->resource();
            $this->rules->reach(Check::onClass($resource, 'viewAny', $path));
        }

        return $relations;
    }

    private function copy(Resource $resource, SearchGrammar $grammar, int $filterDepth, int $includeDepth): self
    {
        return new self($resource, $grammar, $this->limits, $this->rules, $this->reader, $this->vocabulary, $filterDepth, $includeDepth);
    }
}
