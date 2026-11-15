<?php

namespace Lomkit\Rest\Http;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use JsonSerializable;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Extensions\Extension;
use stdClass;

/** @internal */
final readonly class ExtensionMetaValues
{
    public const int DEPTH = 256;

    /**
     * @template TExtension of Extension
     *
     * @param  array<string, mixed>  $own
     * @param  array<string, TExtension>  $extensions
     * @param  Closure(TExtension): array<array-key, mixed>  $values
     * @return array<string, stdClass>
     */
    public static function collect(Resource $resource, string $method, array $own, array $extensions, Closure $values): array
    {
        $added = [];

        foreach ($extensions as $name => $extension) {
            if (array_key_exists($name, $own)) {
                throw InvalidMetaExtensionException::keyTaken($name, $extension::class, $resource::class, $method);
            }

            $meta = $values($extension);
            self::ensureEncodable($meta, $name, 1, fn (string $path, string $type): InvalidMetaExtensionException => InvalidMetaExtensionException::notEncodable($name, $extension::class, $resource::class, $method, $path, $type));
            self::ensureJson($meta, fn (string $reason): InvalidMetaExtensionException => InvalidMetaExtensionException::notJson($name, $extension::class, $resource::class, $method, $reason));
            $added[$name] = (object) $meta;
        }

        return $added;
    }

    /** @param Closure(string, string): InvalidMetaExtensionException $refuse */
    private static function ensureEncodable(mixed $value, string $path, int $depth, Closure $refuse): void
    {
        if ($value instanceof Model || $value instanceof EloquentCollection) {
            throw $refuse($path, get_debug_type($value));
        }

        if ($value === null || is_bool($value) || is_int($value) || is_string($value) || $value instanceof JsonSerializable || $value instanceof BackedEnum) {
            return;
        }

        if (is_float($value) && is_finite($value)) {
            return;
        }

        $children = is_array($value) ? $value : ($value instanceof stdClass ? get_object_vars($value) : null);

        if ($children === null) {
            throw $refuse($path, get_debug_type($value));
        }

        if ($depth > self::DEPTH) {
            return;
        }

        foreach ($children as $key => $child) {
            self::ensureEncodable($child, "{$path}.{$key}", $depth + 1, $refuse);
        }
    }

    /**
     * @param  array<array-key, mixed>  $meta
     * @param  Closure(string): InvalidMetaExtensionException  $refuse
     */
    private static function ensureJson(array $meta, Closure $refuse): void
    {
        try {
            json_encode($meta, JSON_THROW_ON_ERROR, self::DEPTH);
        } catch (JsonException $exception) {
            throw $refuse($exception->getMessage());
        }
    }
}
