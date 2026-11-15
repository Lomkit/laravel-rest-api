<?php

namespace Lomkit\Rest\Authorization;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;
use stdClass;

/** @internal */
final class CastedAttributes
{
    /** @var list<ReflectionProperty>|null */
    private static ?array $caches = null;

    /** @return list<string> */
    public static function keys(Model $row): array
    {
        $keys = [];

        foreach (self::$caches ??= [new ReflectionProperty(Model::class, 'classCastCache'), new ReflectionProperty(Model::class, 'attributeCastCache')] as $cache) {
            $values = $cache->getValue($row);

            if (is_array($values)) {
                array_push($keys, ...array_map(strval(...), array_keys($values)));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $now
     * @param  array<string, mixed>  $before
     */
    public static function equivalent(Model $row, string $key, array $now, array $before): bool
    {
        $probe = (clone $row)->setRawAttributes([]);
        $probe->setRawAttributes($before, true);
        $probe->setRawAttributes($now);

        return $probe->originalIsEquivalent($key)
            || self::read($row, $key, $now) === self::read($row, $key, $before);
    }

    /** @param array<string, mixed> $attributes */
    private static function read(Model $row, string $key, array $attributes): mixed
    {
        $probe = (clone $row)->setRawAttributes($attributes);

        return self::plain($probe->getAttribute($key));
    }

    private static function plain(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value === null || is_scalar($value) ? $value : new stdClass;
    }
}
