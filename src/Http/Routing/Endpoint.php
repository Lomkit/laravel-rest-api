<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Exceptions\InvalidResourceRouteException;

enum Endpoint: string
{
    case Details = 'details';
    case Search = 'search';
    case Mutate = 'mutate';
    case Operate = 'operate';
    case Metric = 'metric';
    case Destroy = 'destroy';
    case Restore = 'restore';
    case ForceDestroy = 'forceDestroy';
    case Batch = 'batch';

    public static function fromKey(self|string $key): self
    {
        if ($key instanceof self) {
            return $key;
        }

        return self::tryFrom($key) ?? throw InvalidResourceRouteException::unknownEndpoint($key);
    }

    /**
     * @param  self|string|list<self|string>  ...$keys
     * @return list<self>
     */
    public static function list(self|string|array ...$keys): array
    {
        $endpoints = [];

        foreach ($keys as $key) {
            foreach (is_array($key) ? $key : [$key] as $item) {
                $endpoints[] = self::fromKey($item);
            }
        }

        return $endpoints;
    }

    /** @return list<self> */
    public static function softDeletes(): array
    {
        return [self::Restore, self::ForceDestroy];
    }

    /** @return list<string> */
    public function methods(): array
    {
        return match ($this) {
            self::Details, self::Batch => ['GET', 'HEAD'],
            self::Search, self::Mutate, self::Operate, self::Metric, self::Restore => ['POST'],
            self::Destroy, self::ForceDestroy => ['DELETE'],
        };
    }

    public function verbKey(): ?string
    {
        return match ($this) {
            self::Details, self::Destroy => null,
            self::Search, self::Mutate, self::Restore, self::ForceDestroy => $this->value,
            self::Operate, self::Batch => 'actions',
            self::Metric => 'metrics',
        };
    }

    public function defaultVerb(): ?string
    {
        return match ($this) {
            self::Details, self::Destroy => null,
            self::Search, self::Mutate, self::Restore => $this->value,
            self::Operate, self::Batch => 'actions',
            self::Metric => 'metrics',
            self::ForceDestroy => 'force',
        };
    }

    public function parameter(): ?string
    {
        return match ($this) {
            self::Operate, self::Batch => 'action',
            self::Metric => 'metric',
            self::Details, self::Search, self::Mutate, self::Destroy, self::Restore, self::ForceDestroy => null,
        };
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::Details, self::Search, self::Mutate, self::Destroy, self::Restore, self::ForceDestroy => ['viewAny'],
            self::Operate, self::Metric, self::Batch => [],
        };
    }

    public function isOptIn(): bool
    {
        return match ($this) {
            self::Restore, self::ForceDestroy, self::Batch => true,
            self::Details, self::Search, self::Mutate, self::Operate, self::Metric, self::Destroy => false,
        };
    }

    public function uri(UriSegment $name, ResourceVerbs $verbs, ?string $key = null): string
    {
        $segments = [$name->value, $verbs->for($this)];

        if ($this->parameter() !== null) {
            $segments[] = $key ?? '{'.$this->parameter().'}';
        }

        if ($this === self::Batch) {
            $segments[] = 'batches/{batch}';
        }

        return implode('/', array_filter($segments, fn (?string $segment): bool => $segment !== null));
    }
}
