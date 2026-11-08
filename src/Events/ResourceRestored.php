<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Lomkit\Rest\Http\Resource;

final readonly class ResourceRestored implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    /**
     * @param  class-string<Resource>  $resource
     * @param  Collection<int, Model>  $models
     */
    public function __construct(
        public string $resource,
        public Collection $models,
    ) {}

    /** @param Collection<int, Model> $models */
    public static function of(Resource $resource, Collection $models): self
    {
        return new self($resource::class, $models);
    }
}
