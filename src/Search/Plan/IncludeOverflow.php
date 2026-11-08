<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Query\Clauses\Clause;
use WeakMap;

final readonly class IncludeOverflow
{
    /** @param WeakMap<Model, list<string>> $cut */
    private function __construct(
        private WeakMap $cut,
    ) {}

    public static function none(): self
    {
        /** @var WeakMap<Model, list<string>> $cut */
        $cut = new WeakMap;

        return new self($cut);
    }

    /** @param array<array-key, Model> $models */
    public static function trim(QueryPlan $plan, array $models): self
    {
        $overflow = self::none();
        $overflow->level($plan, array_values($models));

        return $overflow;
    }

    public static function limits(Clause $include): bool
    {
        return self::limited($include) !== null;
    }

    /** @return list<string> */
    public function of(Model $model): array
    {
        return $this->cut[$model] ?? [];
    }

    /** @param list<Model> $models */
    private function level(QueryPlan $plan, array $models): void
    {
        foreach ($plan->includes as $include) {
            $this->cutBack($include, $models);

            foreach (IncludeLevels::of($include, $models) as [$level, $rows]) {
                $this->level($level, $rows);
            }
        }
    }

    /** @param list<Model> $models */
    private function cutBack(Clause $include, array $models): void
    {
        $limited = self::limited($include);

        if ($limited === null) {
            return;
        }

        [$name, $limit] = $limited;

        foreach ($models as $model) {
            $rows = $model->relationLoaded($name) ? $model->getRelation($name) : null;

            if (! $rows instanceof Collection || $rows->count() <= $limit) {
                continue;
            }

            $model->setRelation($name, $rows->take($limit));
            $this->cut[$model] = [...$this->of($model), $name];
        }
    }

    /** @return array{string, int}|null */
    private static function limited(Clause $include): ?array
    {
        [$name, $limit] = match (true) {
            $include instanceof AliasedIncludeClause => [$include->alias, $include->plan->limit->parentLimit()],
            $include instanceof IncludeClause => [$include->relation, $include->plan->limit->parentLimit()],
            default => [null, null],
        };

        return $name === null || $limit === null ? null : [$name, $limit];
    }
}
