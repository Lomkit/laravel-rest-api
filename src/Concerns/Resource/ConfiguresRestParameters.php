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
    public function getScopes(RestRequest $request): array
    {
        return $this->context()->definition($this)->scopes();
    }

    public function scope(RestRequest $request, string $name): ?Scope
    {
        foreach ($this->getScopes($request) as $scope) {
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

    /** @return list<int> */
    public function getLimits(RestRequest $request): array
    {
        return $this->context()->definition($this)->limits();
    }
}
