<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** @internal */
final readonly class LoadedRelations
{
    /**
     * @param  array<array-key, mixed>  $names
     * @param  list<mixed>  $shape
     */
    private function __construct(
        private array $names,
        private array $shape,
    ) {}

    public static function of(Model $row, ?self $start = null): self
    {
        $seen = [];
        $names = $start === null ? self::names($row, $seen) : $start->names;
        $seen = [];

        return new self($names, self::shape($row, $names, $seen));
    }

    public function keep(Model $row): bool
    {
        $seen = [];

        return self::shape($row, $this->names, $seen) === $this->shape;
    }

    /**
     * @param  array<int, true>  $seen
     * @return array<array-key, mixed>
     */
    private static function names(Model $model, array &$seen): array
    {
        $seen[spl_object_id($model)] = true;
        $names = [];

        foreach ($model->getRelations() as $name => $related) {
            $nested = [];

            foreach (self::models($related) as $child) {
                if (! isset($seen[spl_object_id($child)])) {
                    $nested = array_replace_recursive($nested, self::names($child, $seen));
                }
            }

            $names[$name] = $nested;
        }

        return $names;
    }

    /**
     * @param  array<array-key, mixed>  $names
     * @param  array<int, true>  $seen
     * @return list<mixed>
     */
    private static function shape(mixed $related, array $names, array &$seen): array
    {
        if ($related instanceof Collection) {
            $items = [];

            foreach ($related->all() as $key => $item) {
                $items[$key] = self::shape($item, $names, $seen);
            }

            return [$related, $items];
        }

        if (! $related instanceof Model || isset($seen[spl_object_id($related)])) {
            return [$related];
        }

        $seen[spl_object_id($related)] = true;
        $relations = $related->getRelations();
        $loaded = [];

        foreach ($names as $name => $nested) {
            $loaded[$name] = array_key_exists($name, $relations) ? self::shape($relations[$name], is_array($nested) ? $nested : [], $seen) : null;
        }

        return [$related, $loaded];
    }

    /** @return list<Model> */
    private static function models(mixed $related): array
    {
        if ($related instanceof Model) {
            return [$related];
        }

        if (! $related instanceof Collection) {
            return [];
        }

        return array_values(array_filter($related->all(), static fn (mixed $item): bool => $item instanceof Model));
    }
}
