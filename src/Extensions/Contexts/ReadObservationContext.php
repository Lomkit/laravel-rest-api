<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;

final readonly class ReadObservationContext
{
    /**
     * @internal
     *
     * @param  list<Model>  $rows
     * @param  array<array-key, mixed>|null  $result
     * @param  array<array-key, mixed>|null  $details
     */
    public function __construct(
        public Resource $resource,
        public Endpoint $endpoint,
        public ?Authenticatable $user,
        public array $rows = [],
        public ?int $total = null,
        public ?int $page = null,
        public ?int $perPage = null,
        public ?Metric $metric = null,
        public ?array $result = null,
        public ?array $details = null,
    ) {}
}
