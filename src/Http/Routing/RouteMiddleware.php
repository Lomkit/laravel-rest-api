<?php

namespace Lomkit\Rest\Http\Routing;

final readonly class RouteMiddleware
{
    /**
     * @param  list<string>  $included
     * @param  list<string>  $excluded
     */
    public function __construct(
        public array $included = [],
        public array $excluded = [],
    ) {}

    /** @param list<string> $middleware */
    public function with(array $middleware): self
    {
        return new self(self::unique([...$this->included, ...$middleware]), $this->excluded);
    }

    /** @param list<string> $middleware */
    public function without(array $middleware): self
    {
        return new self($this->included, self::unique([...$this->excluded, ...$middleware]));
    }

    public function merge(self $other): self
    {
        return $this->with($other->included)->without($other->excluded);
    }

    /**
     * @param  list<string>  $middleware
     * @return list<string>
     */
    private static function unique(array $middleware): array
    {
        return array_values(array_unique($middleware));
    }
}
