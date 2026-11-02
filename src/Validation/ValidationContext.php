<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\Search\SearchGrammar;

final readonly class ValidationContext
{
    public function __construct(
        public Resource $resource,
        public SearchGrammar $grammar,
        public Limits $limits,
        public ParsedRules $rules,
        public PayloadReader $reader,
        public Vocabulary $vocabulary,
        public int $filterDepth = 0,
        public int $includeDepth = 0,
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

    public function inMode(SearchGrammar $grammar): self
    {
        return $this->copy($this->resource, $grammar, $this->filterDepth, $this->includeDepth);
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

    public function reachRelation(string $relation, string $path): ?Resource
    {
        $resource = $this->resource;

        foreach (explode('.', $relation) as $segment) {
            $resource = $resource->relation($segment)?->resource();

            if ($resource === null) {
                return null;
            }

            $this->rules->reach(Check::onClass($resource, 'viewAny', $path));
        }

        return $resource;
    }

    private function copy(Resource $resource, SearchGrammar $grammar, int $filterDepth, int $includeDepth): self
    {
        return new self($resource, $grammar, $this->limits, $this->rules, $this->reader, $this->vocabulary, $filterDepth, $includeDepth);
    }
}
