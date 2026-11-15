<?php

namespace Lomkit\Rest\Authorization;

use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Http\Resource;

/** @internal */
final class CacheContributions
{
    public const string KEY = '/^[a-zA-Z][a-zA-Z0-9_]{0,63}\z/';

    public const string SEPARATOR = '.';

    /** @var array<string, int|float|string|bool|null> */
    private array $items = [];

    /** @var array<string, int> */
    private array $shortened = [];

    /** @var array<string, true> */
    private array $forgotten = [];

    /** @param class-string<Resource> $resource */
    public function __construct(private readonly string $resource) {}

    public function put(string $extension, string $key, int|float|string|bool|null $value): void
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw InvalidAuthorizationExtensionException::invalidContextKey($extension, $this->resource, $key);
        }

        if (is_float($value) && ! is_finite($value)) {
            throw InvalidAuthorizationExtensionException::invalidContextValue($extension, $this->resource, $key);
        }

        $this->items[$extension.self::SEPARATOR.$key] = $value;
    }

    public function shorten(string $extension, string $ability, int $seconds): void
    {
        if ($seconds < 1) {
            throw InvalidAuthorizationExtensionException::invalidCacheDuration($extension, $this->resource, $ability, $seconds);
        }

        $this->shortened[$ability] = min($this->shortened[$ability] ?? $seconds, $seconds);
    }

    public function forget(string $ability): void
    {
        $this->forgotten[$ability] = true;
    }

    /** @return array<string, int|float|string|bool|null> */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @param  array<string, int>  $seconds
     * @return array<string, int>
     */
    public function narrow(array $seconds): array
    {
        $narrowed = [];

        foreach ($seconds as $ability => $duration) {
            if (! isset($this->forgotten[$ability])) {
                $narrowed[$ability] = min($duration, $this->shortened[$ability] ?? $duration);
            }
        }

        return $narrowed;
    }
}
