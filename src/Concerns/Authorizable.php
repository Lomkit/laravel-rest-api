<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Routing\Endpoint;

trait Authorizable
{
    /** @return list<string> */
    public function abilities(Endpoint $endpoint): array
    {
        return $endpoint->abilities();
    }

    /** @return list<string> */
    public function gates(): array
    {
        return [];
    }

    /** @return array<string, int> */
    public function authorizationCache(): array
    {
        return [];
    }

    /** @param list<Model|Changes> $arguments */
    public function authorizeTo(string $ability, Model|string $model, array $arguments = []): void
    {
        $this->authorizedTo($ability, $model, $arguments)->authorize();
    }

    /** @param list<Model|Changes> $arguments */
    public function authorizedTo(string $ability, Model|string $model, array $arguments = []): Response
    {
        return $this
            ->context()
            ->authorizer()
            ->inspect(new Check($this, $ability, $model, related: $arguments))
            ->toResponse();
    }
}
