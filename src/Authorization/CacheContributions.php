<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Support\Str;
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

    /** @var array<string, string> */
    private array $classes = [];

    /** @param class-string<Resource> $resource */
    public function __construct(private readonly string $resource) {}

    public function put(string $name, string $class, string $key, int|float|string|bool|null $value): void
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw InvalidAuthorizationExtensionException::invalidContextKey($name, $class, $this->resource, $key);
        }

        if (is_float($value) && ! is_finite($value)) {
            throw InvalidAuthorizationExtensionException::invalidContextValue($name, $class, $this->resource, $key);
        }

        $this->items[$name.self::SEPARATOR.$key] = $value;
        $this->classes[$name] = $class;
    }

    public function shorten(string $name, string $class, string $ability, int $seconds): void
    {
        if ($seconds < 1) {
            throw InvalidAuthorizationExtensionException::invalidCacheDuration($name, $class, $this->resource, $ability, $seconds);
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

    public function keyTaken(string $item): InvalidAuthorizationExtensionException
    {
        $name = Str::before($item, self::SEPARATOR);

        return InvalidAuthorizationExtensionException::contextKeyTaken($name, $this->classes[$name], $this->resource, $item);
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
