<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;

/** @internal */
final readonly class RowState
{
    private const array CAST_CACHES = ['classCastCache', 'attributeCastCache'];

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $attributes
     * @param  array<array-key, mixed>  $relations
     */
    private function __construct(
        private Model $row,
        private array $original,
        private array $attributes,
        private array $relations,
    ) {}

    public static function of(Model $row): self
    {
        return new self($row, $row->getRawOriginal(), self::stored($row, self::castKeys($row)), $row->getRelations());
    }

    public function keeps(Model $row): bool
    {
        if ($row !== $this->row || $row->getRawOriginal() !== $this->original) {
            return false;
        }

        $relations = $row->getRelations();

        foreach ($this->relations as $name => $related) {
            if (! array_key_exists($name, $relations) || $relations[$name] !== $related) {
                return false;
            }
        }

        return $this->keepsAttributes($row);
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

        return self::stored($row, $keys) === $read->getAttributes();
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
