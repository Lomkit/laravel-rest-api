<?php

namespace Lomkit\Rest\Http\Routing;

final readonly class EndpointSelection
{
    /**
     * @param  list<Endpoint>  $optedIn
     * @param  list<Endpoint>|null  $only
     * @param  list<Endpoint>  $except
     */
    public function __construct(
        private array $optedIn = [],
        private ?array $only = null,
        private array $except = [],
    ) {}

    /** @param list<Endpoint> $endpoints */
    public function only(array $endpoints): self
    {
        return new self($this->optedIn, $endpoints, $this->except);
    }

    /** @param list<Endpoint> $endpoints */
    public function except(array $endpoints): self
    {
        return new self($this->optedIn, $this->only, $endpoints);
    }

    /** @param list<Endpoint> $endpoints */
    public function optIn(array $endpoints): self
    {
        return new self([...$this->optedIn, ...$endpoints], $this->only, $this->except);
    }

    /** @return list<Endpoint> */
    public function resolve(): array
    {
        return array_values(array_filter(Endpoint::cases(), fn (Endpoint $endpoint): bool => $this->includes($endpoint)));
    }

    private function includes(Endpoint $endpoint): bool
    {
        if ($endpoint->isOptIn() && ! in_array($endpoint, $this->optedIn, true)) {
            return false;
        }

        if ($this->only !== null && ! in_array($endpoint, $this->only, true)) {
            return false;
        }

        return ! in_array($endpoint, $this->except, true);
    }
}
