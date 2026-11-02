<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Scopes\Scope;

final readonly class ScopeNode
{
    public function __construct(
        public Scope $scope,
        public InputValues $parameters,
    ) {}
}
