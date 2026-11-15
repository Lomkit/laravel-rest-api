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
        private string $class,
        public Resource $resource,
        public ?Authenticatable $user,
        public ResourceLimits $declared,
        private LimitRequests $requests,
    ) {}

    /** @param list<int> $sizes */
    public function allowSizes(array $sizes): void
    {
        $this->requests->allowSizes($this->extension, $this->class, $sizes);
    }

    public function maxSize(int $size): void
    {
        $this->requests->maxSize($this->extension, $this->class, $size);
    }

    public function maxTotal(int $total): void
    {
        $this->requests->maxTotal($this->extension, $this->class, $total);
    }

    public function includeLimit(int $limit): void
    {
        $this->requests->includeLimit($this->extension, $this->class, $limit);
    }
}
