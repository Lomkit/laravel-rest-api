<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class ColumnSet implements Clause
{
    /** @param list<string> $columns */
    private function __construct(public array $columns) {}

    /** @param list<string> $columns */
    public static function of(Model $model, array $columns): self
    {
        $qualified = array_values(array_unique(array_map($model->qualifyColumn(...), $columns)));
        $every = $model->qualifyColumn('*');

        return new self(in_array($every, $qualified, true) ? [$every] : $qualified);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query = match (true) {
            $query instanceof EloquentBuilder => $query,
            $query instanceof Relation => $query->getQuery(),
            default => throw new InvalidArgumentException('A column set applies to an Eloquent builder or relation, not to '.get_debug_type($query).'.'),
        };
        $model = $query->getModel();
        $columns = self::of($model, [...$this->columns, ...self::eagerKeys($query)])->columns;
        $selected = $query->getQuery()->columns;

        if ($selected === null) {
            $query->select($columns);

            return;
        }

        $query->getQuery()->columns = self::merge($model, $selected, $columns);
    }

    /**
     * @param  array<Expression|string>  $selected
     * @param  list<string>  $columns
     * @return list<Expression|string>
     */
    private static function merge(Model $model, array $selected, array $columns): array
    {
        $every = ['*', $model->qualifyColumn('*')];
        $listed = array_map($model->qualifyColumn(...), array_filter($selected, static fn (Expression|string $entry): bool => is_string($entry) && ! in_array($entry, $every, true)));
        $missing = array_values(array_diff($columns, $listed));
        $merged = [];

        foreach ($selected as $entry) {
            if (! in_array($entry, $every, true)) {
                $merged[] = $entry;

                continue;
            }

            array_push($merged, ...$missing);
            $missing = [];
        }

        return [...$merged, ...$missing];
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @return list<string>
     */
    private static function eagerKeys(EloquentBuilder $query): array
    {
        $keys = [];
        $scoped = $query->applyScopes();

        foreach (array_keys($scoped->getEagerLoads()) as $name) {
            if (! str_contains($name, '.')) {
                array_push($keys, ...RelationKeys::of($scoped->getRelation($name))->parent());
            }
        }

        return $keys;
    }
}
