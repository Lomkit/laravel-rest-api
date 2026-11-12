<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Support\Overrides;
use ReflectionClass;

final class DeclaredRoute
{
    /** @var array<string, self> */
    private static array $classes = [];

    /**
     * @param  list<string>  $middleware
     * @param  list<string>  $withoutMiddleware
     */
    private function __construct(
        public readonly string $key,
        public readonly array $middleware,
        public readonly array $withoutMiddleware,
    ) {}

    public static function ofInstance(Action|Metric $declaration): self
    {
        return new self($declaration->uriKey(), $declaration->middleware, $declaration->withoutMiddleware);
    }

    public static function ofClass(string $class, DeclarationKind $kind): self
    {
        return self::$classes[$class] ??= self::read($class, $kind);
    }

    public function equals(self $other): bool
    {
        return [$this->key, $this->middleware, $this->withoutMiddleware] === [$other->key, $other->middleware, $other->withoutMiddleware];
    }

    private static function read(string $class, DeclarationKind $kind): self
    {
        if (! is_subclass_of($class, $kind->base())) {
            throw $kind->notDeclarable($class);
        }

        foreach (['name', 'uriKey'] as $method) {
            if (Overrides::method($class, $method, $kind->base())) {
                throw $kind->dynamicKey($class, $method);
            }
        }

        $defaults = (new ReflectionClass($class))->getDefaultProperties();
        $name = $defaults['name'] ?? null;

        return new self(
            $kind->uriKey($kind->displayName(is_string($name) ? $name : null, $class)),
            self::middlewareList($defaults['middleware'] ?? []),
            self::middlewareList($defaults['withoutMiddleware'] ?? []),
        );
    }

    /** @return list<string> */
    private static function middlewareList(mixed $middleware): array
    {
        return is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : [];
    }
}
