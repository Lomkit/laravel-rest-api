<?php

namespace Lomkit\Rest\Http;

use Illuminate\Auth\AuthManager;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\CacheDecisionStore;
use Lomkit\Rest\Authorization\DecisionStore;
use Lomkit\Rest\Authorization\NullDecisionStore;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Http\Requests\RestRequest;
use WeakMap;

final class RestContext
{
    private readonly ResourceRegistry $resources;

    /** @var WeakMap<Resource, ResourceDefinition> */
    private readonly WeakMap $definitions;

    private ?bool $textSearch = null;

    private ?int $maxNestingDepth = null;

    private ?Authorizer $authorizer = null;

    private ?string $authorizerGuard = null;

    private ?Authenticatable $authorizerUser = null;

    public function __construct(
        public readonly RestRequest $request,
        private readonly Container $container,
    ) {
        $this->resources = new ResourceRegistry($container);
        $this->definitions = new WeakMap;
    }

    /** @param class-string<Resource> $class */
    public function resource(string $class): Resource
    {
        return $this->resources->get($class)->withinContext($this);
    }

    public function definition(Resource $resource): ResourceDefinition
    {
        return $this->definitions[$resource] ??= new ResourceDefinition($resource, $this->request);
    }

    public function isTextSearch(): bool
    {
        return $this->textSearch ??= filled($this->request->input('search.text.value'));
    }

    public function maxNestingDepth(): int
    {
        if ($this->maxNestingDepth !== null) {
            return $this->maxNestingDepth;
        }

        $depth = config('rest.search.max_nesting_depth', 1);

        return $this->maxNestingDepth = is_numeric($depth) ? (int) $depth : 1;
    }

    public function authorizer(): Authorizer
    {
        $auth = $this->container->make(AuthManager::class);
        $guard = $auth->getDefaultDriver();
        $user = $this->request->user() ?? $auth->guard()->user();

        if ($this->authorizer !== null && $guard === $this->authorizerGuard && $user === $this->authorizerUser) {
            return $this->authorizer;
        }

        [$this->authorizerGuard, $this->authorizerUser] = [$guard, $user];

        return $this->authorizer = new Authorizer(
            $this->container->make(Gate::class)->forUser($user),
            $this->decisionStore($guard, $user),
            strict: (bool) config('rest.authorizations.strict', false),
        );
    }

    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        $this->__construct(RestRequest::createFrom(request()), IlluminateContainer::getInstance());
    }

    private function decisionStore(string $guard, ?Authenticatable $user): DecisionStore
    {
        if (! config('rest.authorizations.cache.enabled', false)) {
            return new NullDecisionStore;
        }

        return new CacheDecisionStore($this->container->make(Repository::class), UserIdentity::of($guard, $user));
    }
}
