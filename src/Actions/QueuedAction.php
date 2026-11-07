<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Auth\AuthManager;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Exceptions\QueuedActionException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

final class QueuedAction implements ShouldQueue
{
    use Batchable;
    use InteractsWithQueue;
    use Queueable;

    public ?int $tries = null;

    public ?int $timeout = null;

    /** @var array<int>|int|null */
    public array|int|null $backoff = null;

    public ?int $maxExceptions = null;

    public bool $failOnTimeout = false;

    /**
     * @param  class-string<Resource>  $resource
     * @param  class-string<Action>  $action
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $action,
        public readonly string $uriKey,
        public readonly array $fields,
        public readonly QueuedRows $rows,
        public readonly UserReference $user,
    ) {}

    /** @param array<string, mixed> $fields */
    public static function of(Action $action, array $fields, QueuedRows $rows, UserReference $user): self
    {
        return (new QueueSettings($action))->configure(new self($action->resource::class, $action::class, $action->uriKey(), $fields, $rows, $user));
    }

    public function handle(Container $container, AuthManager $auth): void
    {
        $guard = $auth->getDefaultDriver();
        $auth->shouldUse($this->user->guard);

        try {
            $this->run($container, $this->user->resolve($auth));
        } finally {
            $auth->shouldUse($guard);
        }
    }

    public function displayName(): string
    {
        return $this->action;
    }

    private function run(Container $container, ?Authenticatable $user): void
    {
        $request = RestRequest::create('/')->setContainer($container);
        $request->setUserResolver(fn (): ?Authenticatable => $user);
        $resource = $request->context()->resource($this->resource);
        $action = $this->declared($resource);
        $models = $this->rows->load($resource);

        if ($this->rows->keys !== [] && $models->isEmpty()) {
            return;
        }

        if ($this->job !== null) {
            $action->setJob($this->job);
        }

        if ($this->batchId !== null) {
            $action->withBatchId($this->batchId);
        }

        $inputs = InputValues::fromGiven($resource->definition()->inputFields($action), $this->fields);

        $action->handle(ActionFields::of($action, $inputs, $this->user->guard, $user), $models);
    }

    private function declared(Resource $resource): Action
    {
        $definition = $resource->definition();
        $action = $definition->actionExists($this->uriKey) ? $definition->action($this->uriKey) : null;

        if (! $action instanceof $this->action) {
            throw QueuedActionException::missingAction($this->resource, $this->action, $this->uriKey);
        }

        return $action;
    }
}
