<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidScopeException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Resource;

final readonly class ScopeClause implements Clause
{
    /** @param list<mixed> $parameters */
    public function __construct(
        private Resource $resource,
        private string $name,
        private array $parameters = [],
    ) {}

    public function apply(Builder $query): void
    {
        $scope = $this->resource->scope($this->resource->context()->request, $this->name)
            ?? throw InvalidScopeException::undeclared($this->resource::class, $this->name);

        $query->scopes([$this->name => InputValues::fromPositional($scope->getFields(), $this->parameters)->arguments()]);
    }
}
