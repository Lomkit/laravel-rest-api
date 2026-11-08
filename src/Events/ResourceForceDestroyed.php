<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class ResourceForceDestroyed implements ShouldDispatchAfterCommit
{
    /**
     * @param  class-string<Resource>  $resource
     * @param  class-string<Model>  $model
     * @param  list<int|string>  $keys
     */
    public function __construct(
        public string $resource,
        public string $model,
        public array $keys,
    ) {}

    /** @param Collection<int, Model> $models */
    public static function of(Resource $resource, Collection $models): self
    {
        return new self($resource::class, $resource::modelClass(), array_values($models->modelKeys()));
    }
}
