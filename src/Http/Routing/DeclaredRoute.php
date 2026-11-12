<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Support\DeclarationName;
use Lomkit\Rest\Support\Overrides;
use ReflectionClass;

final readonly class DeclaredRoute
{
    /**
     * @param  list<string>  $middleware
     * @param  list<string>  $withoutMiddleware
     */
    private function __construct(
        public string $key,
        public array $middleware,
        public array $withoutMiddleware,
    ) {}

    public static function ofInstance(Action|Metric $declaration): self
    {
        return new self($declaration->uriKey(), $declaration->middleware, $declaration->withoutMiddleware);
    }

    public static function ofClass(string $class, DeclarationKind $kind): self
    {
        return ClassRoutes::remember($kind, $class, fn (): self => self::read($class, $kind));
    }

    public function equals(self $other): bool
    {
        return $this->fingerprint() === $other->fingerprint();
    }

    public function fingerprint(): string
    {
        return hash('xxh128', json_encode([$this->key, $this->middleware, $this->withoutMiddleware], JSON_THROW_ON_ERROR));
    }

    private static function read(string $class, DeclarationKind $kind): self
    {
        $declarable = self::declarable($class, $kind);

        foreach (['name', 'uriKey'] as $method) {
            if (Overrides::method($declarable->getName(), $method, $kind->base())) {
                throw $kind->dynamicKey($class, $method);
            }
        }

        $defaults = $declarable->getDefaultProperties();
        $name = $defaults['name'] ?? null;

        return new self(
            DeclarationName::uriKey(DeclarationName::of(is_string($name) ? $name : null, $declarable->getName(), $kind->base())),
            self::middlewareList($defaults['middleware'] ?? []),
            self::middlewareList($defaults['withoutMiddleware'] ?? []),
        );
    }

    /** @return ReflectionClass<Action|Metric> */
    private static function declarable(string $class, DeclarationKind $kind): ReflectionClass
    {
        if ($class === $kind->base()) {
            throw $kind->baseListed();
        }

        if (! is_subclass_of($class, $kind->base())) {
            throw $kind->notDeclarable($class);
        }

        $declarable = new ReflectionClass($class);

        if (! $declarable->isInstantiable()) {
            throw $kind->notInstantiable($class);
        }

        return $declarable;
    }

    /** @return list<string> */
    private static function middlewareList(mixed $middleware): array
    {
        return is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : [];
    }
}
