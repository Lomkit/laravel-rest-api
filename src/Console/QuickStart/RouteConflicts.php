<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Http\Routing\EndpointSelection;
use Lomkit\Rest\Http\Routing\ResourceVerbs;
use Lomkit\Rest\Http\Routing\RouteNames;
use Lomkit\Rest\Http\Routing\UriSegment;

final readonly class RouteConflicts
{
    private const string API_GROUP = 'api';

    private const string PARAMETER = '/\{[^}]*\}/';

    public function __construct(private RouteCollectionInterface $routes) {}

    /** @return list<string> */
    public function of(string $uri): array
    {
        $prefix = $this->apiPrefix();
        $segment = UriSegment::resourceName($uri);
        $verbs = ResourceVerbs::current();
        $names = new RouteNames($uri);
        $conflicts = [];

        foreach ((new EndpointSelection)->resolve() as $endpoint) {
            $path = $this->normalized(trim("{$prefix}/{$endpoint->uri($segment, $verbs)}", '/'));

            foreach ($this->routes->getRoutes() as $route) {
                if ($route->getName() === $names->for($endpoint) || $this->answers($route, $endpoint, $path)) {
                    $conflicts[$this->describe($route)." clashes with Rest::resource('{$uri}')"] = true;
                }
            }
        }

        return array_keys($conflicts);
    }

    private function answers(Route $route, Endpoint $endpoint, string $path): bool
    {
        return $route->getDomain() === null
            && $this->normalized($route->uri()) === $path
            && array_intersect($route->methods(), $endpoint->methods()) !== [];
    }

    private function apiPrefix(): string
    {
        $prefixes = [];

        foreach ($this->routes->getRoutes() as $route) {
            if (in_array(self::API_GROUP, (array) $route->getAction('middleware'), true)) {
                $prefixes[] = trim((string) $route->getPrefix(), '/');
            }
        }

        usort($prefixes, fn (string $first, string $second): int => strlen($first) <=> strlen($second));

        return $prefixes[0] ?? self::API_GROUP;
    }

    private function normalized(string $uri): string
    {
        return (string) preg_replace(self::PARAMETER, '{}', trim($uri, '/'));
    }

    private function describe(Route $route): string
    {
        $name = $route->getName() === null ? '' : " [{$route->getName()}]";

        return implode('|', $route->methods()).' /'.trim($route->uri(), '/').$name.' ('.$route->getActionName().')';
    }
}
