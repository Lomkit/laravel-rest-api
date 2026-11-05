<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Scopes\Scope;

trait ConfiguresRestParameters
{
    /** @return list<Scope> */
    public function scopes(RestRequest $request): array
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

    /** @return list<int> */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /** @return list<string> */
    public function alwaysSelect(RestRequest $request): array
    {
        return [];
    }

    /** @return list<int> */
    public function getLimits(): array
    {
        return $this->context()
            ->definition($this)
            ->limits()
            ->allowed;
    }
}
