<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;

/** @internal */
final readonly class RowState
{
    private const array CAST_CACHES = ['classCastCache', 'attributeCastCache'];

    /**
     * @param  list<mixed>  $settings
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        private Model $row,
        private array $settings,
        private array $original,
        private array $attributes,
        private LoadedRelations $relations,
    ) {}

    public static function of(Model $row, ?self $start = null): self
    {
        $attributes = self::stored($row, self::castKeys($row));

        return new self($row, self::settings($row), $row->getRawOriginal(), $attributes, LoadedRelations::of($row, $start?->relations));
    }

    public function row(): Model
    {
        return $this->row;
    }

    public function keeps(Model $row): bool
    {
        return $row === $this->row
            && self::settings($row) === $this->settings
            && $row->getRawOriginal() === $this->original
            && $this->relations->keep($row)
            && $this->keepsAttributes($row);
    }

    /** @return list<mixed> */
    private static function settings(Model $row): array
    {
        return [$row->exists, $row->getTable(), $row->getConnectionName(), $row->getKeyName(), $row->usesTimestamps(), $row->getCasts()];
    }

    private function keepsAttributes(Model $row): bool
    {
        $keys = self::castKeys($row);

        if ($keys === []) {
            return $row->getAttributes() === $this->attributes;
        }

        $read = clone $row;
        $read->setRawAttributes($this->attributes);

        foreach ($keys as $key) {
            $read->getAttribute($key);
        }

        return self::sameStorage($row, $keys, self::stored($row, $keys), $read->getAttributes());
    }

    /**
     * @param  list<string>  $castKeys
     * @param  array<string, mixed>  $now
     * @param  array<string, mixed>  $before
     */
    private static function sameStorage(Model $row, array $castKeys, array $now, array $before): bool
    {
        if ($now === $before) {
            return true;
        }

        if (array_diff_key($now, $before) !== [] || array_diff_key($before, $now) !== []) {
            return false;
        }

        foreach ($now as $key => $value) {
            if ($value !== $before[$key] && (! in_array($key, $castKeys, true) || ! self::equivalent($row, $key, $value, $before[$key]))) {
                return false;
            }
        }

        return true;
    }

    private static function equivalent(Model $row, string $key, mixed $now, mixed $before): bool
    {
        $probe = (clone $row)->setRawAttributes([]);
        $probe->setRawAttributes([$key => $before], true);
        $probe->setRawAttributes([$key => $now]);

        return $probe->originalIsEquivalent($key);
    }

    /**
     * @param  list<string>  $castKeys
     * @return array<string, mixed>
     */
    private static function stored(Model $row, array $castKeys): array
    {
        return $castKeys === [] ? $row->getAttributes() : (clone $row)->getAttributes();
    }

    /** @return list<string> */
    private static function castKeys(Model $row): array
    {
        $keys = [];

        foreach (self::CAST_CACHES as $cache) {
            $values = (new ReflectionProperty(Model::class, $cache))->getValue($row);

            if (is_array($values)) {
                array_push($keys, ...array_map(strval(...), array_keys($values)));
            }
        }

        return array_values(array_unique($keys));
    }
}
