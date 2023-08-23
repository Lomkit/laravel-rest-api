<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Controllers\Controller;

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
        return array_merge(
            [
                'openapi' => $this->openapi(),
                'info' => $this->info()->jsonSerialize(),
                'paths' => collect($this->paths())->map->jsonSerialize()->toArray()
            ],
            isset($this->servers) ? ['servers' => collect($this->servers())->map->jsonSerialize()->toArray()] : [],
            isset($this->security) ? ['security' => collect($this->security())->map->jsonSerialize()->toArray()] : []
        );
    }

    public function generate(): OpenAPI
    {
        $servers = [];

        foreach (config('rest.documentation.servers') as $server) {
            $serverInstance = (new Server)
                ->withDescription($server['description'] ?? '')
                ->withUrl($server['url']);

            foreach ($server['variables'] ?? [] as $key => $variable) {
                $serverInstance
                    ->withVariable($key, (new ServerVariable)
                        ->withDescription($variable['description'] ?? '')
                        ->withDefault($variable['default'] ?? '')
                        ->withEnum($variable['enum'] ?? []));
            }

            $servers[] = $serverInstance;
        }

        $securities = [];

        foreach (config('rest.documentation.security') as $security) {
            $securityInstance = (new SecurityScheme)
                ->withDescription($security['description'] ?? '')
                ->withIn($security['in'] ?? '')
                ->withType($security['type'] ?? '')
                ->withName($security['name'] ?? '')
                ->withBearerFormat($security['bearerFormat'] ?? '')
                ->withOpenIdConnectUrl($security['openIdConnectUrl'] ?? '')
                ->withScheme($security['scheme'] ?? '')
                ->withFlows($oauthFlows = new OauthFlows());


            foreach ($security['flows'] ?? [] as $key => $flow) {
                $flowInstance = (new OauthFlow)
                    ->withScopes($flow['scopes'] ?? [])
                    ->withAuthorizationUrl($flow['authorizationUrl'] ?? '')
                    ->withTokenUrl($flow['tokenUrl'])
                    ->withRefreshUrl($flow['refreshUrl']);

                $oauthFlows->{'with'.Str::studly($key)}($flowInstance);
            }

            $securities[] = $securityInstance;
        }

        return Rest::applyDocumentationCallback(
            $this
                ->withInfo(
                    (new Info)
                        ->generate()
                )
                ->withPaths(
                    $this->generatePaths()
                )
                ->withSecurity($securities)
                ->withServers($servers)
        );
    }

    public function generatePaths() {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            /** @var \Illuminate\Routing\Route $route */

            if (is_null($route->getName()))
            {
                continue;
            }

            $controller = $route->getController();

            if ($controller instanceof Controller) {
                $path = match (Str::afterLast($route->getName(), '.')) {
                    'detail' => (new Path)->generateDetailAndDestroy($controller),
                    'search' => (new Path)->generateSearch($controller),
                    'mutate' => (new Path)->generateMutate($controller),
                    'operate' => (new Path)->generateActions($controller),
                    'restore' => (new Path)->generateRestore($controller),
                    'forceDelete' => (new Path)->generateForceDelete($controller),
                    default => null
                };

                if (!is_null($path)) {
                    $paths['/'.$route->uri()] = $path;
                }
            }
        }

        return $paths;
    }
}