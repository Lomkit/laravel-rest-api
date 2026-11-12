<?php

namespace Lomkit\Rest\Http;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\CacheDecisionStore;
use Lomkit\Rest\Authorization\DecisionStore;
use Lomkit\Rest\Authorization\NullDecisionStore;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Metrics\CacheMetricStore;
use Lomkit\Rest\Metrics\MetricStore;
use Lomkit\Rest\Metrics\NullMetricStore;
use Lomkit\Rest\Text\ResolvedText;
use Lomkit\Rest\Validation\Limits;
use Lomkit\Rest\Validation\Vocabulary;
use WeakMap;

final class RestContext
{
    private readonly ResourceRegistry $resources;

    /** @var WeakMap<Resource, ResourceDefinition> */
    private readonly WeakMap $definitions;

    private ?RestConfig $config = null;

    private ?Limits $limits = null;

    private ?Vocabulary $vocabulary = null;

    private ?Authorizer $authorizer = null;

    private ?string $authorizerGuard = null;

    private ?Authenticatable $authorizerUser = null;

    public function __construct(
        public readonly RestRequest $request,
        private readonly Container $container,
        private readonly ?ActingUser $actingUser = null,
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
        return $this->definitions[$resource] ??= new ResourceDefinition($resource, $this->request, $this->container);
    }

    public function text(Resource $resource): ResolvedText
    {
        return $this->definition($resource)->text();
    }

    public function config(): RestConfig
    {
        return $this->config ??= RestConfig::from($this->container->make(ConfigRepository::class));
    }

    public function limits(): Limits
    {
        return $this->limits ??= Limits::fromConfig($this->container->make(ConfigRepository::class)->get('rest.limits'));
    }

    public function vocabulary(): Vocabulary
    {
        return $this->vocabulary ??= new Vocabulary;
    }

    public function authorizer(): Authorizer
    {
        $acting = $this->actingUser();
        [$guard, $user] = [$acting->guard, $acting->user];

        if ($this->authorizer !== null && $guard === $this->authorizerGuard && $user === $this->authorizerUser) {
            return $this->authorizer;
        }

        [$this->authorizerGuard, $this->authorizerUser] = [$guard, $user];

        return $this->authorizer = new Authorizer(
            $this->container->make(Gate::class)->forUser($user),
            $this->decisionStore($acting),
            strict: $this->config()->strict,
        );
    }

    public function events(): Dispatcher
    {
        return $this->container->make(Dispatcher::class);
    }

    public function metricStore(): MetricStore
    {
        if (! $this->config()->cacheEnabled) {
            return new NullMetricStore;
        }

        return new CacheMetricStore($this->container->make(Factory::class), $this->actingUser()->identity(), $this->config()->cacheStore);
    }

    public function actingUser(): ActingUser
    {
        if ($this->actingUser !== null) {
            return $this->actingUser;
        }

        $auth = $this->container->make(AuthManager::class);

        return ActingUser::of($auth->getDefaultDriver(), $this->request->user() ?? $auth->guard()->user());
    }

    private function decisionStore(ActingUser $acting): DecisionStore
    {
        if (! $this->config()->cacheEnabled) {
            return new NullDecisionStore;
        }

        return new CacheDecisionStore($this->container->make(Factory::class), $acting->identity(), $this->config()->cacheStore);
    }
}
