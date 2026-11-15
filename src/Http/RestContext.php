<?php

namespace Lomkit\Rest\Http;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\CacheDecisionStore;
use Lomkit\Rest\Authorization\DecisionPreparation;
use Lomkit\Rest\Authorization\DecisionStore;
use Lomkit\Rest\Authorization\NullDecisionStore;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\RuntimeExtensions;
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

    private readonly RuntimeExtensions $registrations;

    private ?RestConfig $config = null;

    private ?ExtensionRegistry $extensions = null;

    private ?Limits $limits = null;

    private ?Vocabulary $vocabulary = null;

    private ?Authorizer $authorizer = null;

    private ?ActingUser $authorizerFor = null;

    private bool $documentation = false;

    public function __construct(
        public readonly RestRequest $request,
        private readonly Container $container,
        private readonly ?ActingUser $actingUser = null,
    ) {
        $this->resources = new ResourceRegistry($container);
        $this->definitions = new WeakMap;
        $this->registrations = $container->make(RuntimeExtensions::class);
        $this->registrations->freeze();
    }

    /** @internal */
    public static function forDocumentation(RestRequest $request, Container $container, ActingUser $guest): self
    {
        $context = new self($request, $container, $guest);
        $context->documentation = true;

        return $context;
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

    /** @internal */
    public function isDocumentation(): bool
    {
        return $this->documentation;
    }

    public function config(): RestConfig
    {
        return $this->config ??= RestConfig::from($this->container->make(ConfigRepository::class));
    }

    public function extensions(): ExtensionRegistry
    {
        return $this->extensions ??= ExtensionRegistry::of($this->config()->extensions, $this->config()->disabledExtensions, $this->registrations->all(), $this->container);
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

        if ($this->authorizer !== null && $this->authorizerFor?->sameAs($acting) === true) {
            return $this->authorizer;
        }

        $this->authorizerFor = $acting;
        $user = $acting->user;

        return $this->authorizer = new Authorizer(
            $this->container->make(Gate::class)->forUser($user),
            $this->decisionStore($acting),
            strict: $this->config()->strict,
            preparation: new DecisionPreparation($user, $this->extensions(...)),
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
