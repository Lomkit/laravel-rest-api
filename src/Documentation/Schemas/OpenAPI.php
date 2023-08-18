<?php

namespace Lomkit\Rest\Documentation\Schemas;

class OpenAPI extends Schema
{
    /**
     * The version number of the OpenAPI specification
     *
     * @var string
     */
    protected string $openapi = '3.1.0';

    /**
     * Provides metadata about the API. The metadata MAY be used by tooling as required.
     *
     * @var Info
     */
    protected Info $info;

    /**
     * The available paths and operations for the API.
     *
     * @var array
     */
    protected array $paths = [];

    /**
     * An array of Server Objects, which provide connectivity information to a target server.
     *
     * @var array
     */
    protected array $servers = [];

    /**
     * A declaration of which security mechanisms can be used across the API.
     *
     * @var array
     */
    protected array $security = [];

    public function openapi(): string
    {
        return $this->openapi;
    }

    public function withOpenapi(string $openapi): self
    {
        $this->openapi = $openapi;

        return $this;
    }

    public function info(): Info
    {
        return $this->info;
    }

    public function withInfo(Info $info): self
    {
        $this->info = $info;

        return $this;
    }

    public function paths(): array
    {
        return $this->paths;
    }

    public function withPaths(array $paths): self
    {
        $this->paths = array_merge($this->paths, $paths);

        return $this;
    }

    public function security(): array
    {
        return $this->security;
    }

    public function withSecurity(array $security): self
    {
        $this->security = $security;

        return $this;
    }

    public function withServers(array $servers): OpenAPI
    {
        $this->servers = $servers;
        return $this;
    }

    public function servers(): array
    {
        return $this->servers;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'openapi' => $this->openapi(),
            'info' => $this->info()->jsonSerialize(),
            'paths' => collect($this->paths())->map->jsonSerialize()->toArray(),
            'servers' => collect($this->servers())->map->jsonSerialize()->toArray(),
            'security' => collect($this->security())->map->jsonSerialize()->toArray()
        ];
    }
}