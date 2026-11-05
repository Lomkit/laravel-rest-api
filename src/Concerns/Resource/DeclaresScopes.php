<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Scopes\Scope;

trait DeclaresScopes
{
    /** @return list<Scope> */
    public function scopes(): array
    {
        return [];
    }

    /** @return list<Scope> */
    public function getScopes(): array
    {
        return $this->context()->definition($this)->scopes();
    }

    public function scope(string $name): ?Scope
    {
        foreach ($this->getScopes() as $scope) {
            if ($scope->name === $name) {
                return $scope;
            }
        }

        return null;
    }
}
