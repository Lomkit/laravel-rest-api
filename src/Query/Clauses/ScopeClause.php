<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Query\WhereGroup;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\ScopeNode;

final readonly class ScopeClause implements Clause
{
    private function __construct(
        private Scope $scope,
        private InputValues $parameters,
    ) {}

    public static function of(ScopeNode $node): self
    {
        return new self($node->scope, $node->parameters);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        WhereGroup::wrap($query, function (EloquentBuilder $query): void {
            $query->scopes([$this->scope->name => $this->parameters->arguments()]);
        });
    }
}
