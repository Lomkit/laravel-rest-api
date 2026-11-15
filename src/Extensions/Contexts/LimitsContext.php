<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Http\LimitRequests;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\ResourceLimits;

final readonly class LimitsContext
{
    /** @internal */
    public function __construct(
        private string $extension,
        public Resource $resource,
        public ?Authenticatable $user,
        public ResourceLimits $declared,
        private LimitRequests $requests,
    ) {}

    /** @param list<int> $sizes */
    public function allowSizes(array $sizes): void
    {
        $this->requests->allowSizes($this->extension, $sizes);
    }

    public function maxSize(int $size): void
    {
        $this->requests->maxSize($this->extension, $size);
    }

    public function maxTotal(int $total): void
    {
        $this->requests->maxTotal($this->extension, $total);
    }

    public function includeLimit(int $limit): void
    {
        $this->requests->includeLimit($this->extension, $limit);
    }
}
