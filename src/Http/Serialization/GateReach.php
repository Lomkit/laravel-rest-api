<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Lomkit\Rest\Authorization\Ability;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Validation\KeysGrammar;

final readonly class GateReach
{
    /** @param array<string, array<array-key, true>> $outside */
    private function __construct(private array $outside) {}

    public static function everyRow(): self
    {
        return new self([]);
    }

    /** @param list<Model> $models */
    public static function of(QueryPlan $plan, array $models): self
    {
        if (! $plan->resource->isGatingEnabled()) {
            return self::everyRow();
        }

        $gated = array_map(static fn (GateAbility $gate): Ability|string => $gate->ability, $plan->gates ?? []);
        $perimeter = self::holds($plan, PerimeterClause::class);
        $trashed = self::holds($plan, TrashedClause::class);
        $live = self::gates($gated, Ability::Update, Ability::Delete) && (! $perimeter || $trashed)
            ? self::outside($plan->resource, $models, false)
            : null;
        $any = self::gates($gated, Ability::Restore, Ability::ForceDelete) && ! $perimeter
            ? ($trashed || $live === null ? self::outside($plan->resource, $models, true) : $live)
            : null;

        return new self(array_filter([
            Ability::Update->value => $live,
            Ability::Delete->value => $live,
            Ability::Restore->value => $any,
            Ability::ForceDelete->value => $any,
        ], static fn (?array $keys): bool => $keys !== null));
    }

    public function reaches(GateAbility $gate, Model $model): bool
    {
        return ! isset($this->outside[$gate->name()][self::key($model->getKey())]);
    }

    /** @param list<Ability|string> $gated */
    private static function gates(array $gated, Ability ...$abilities): bool
    {
        foreach ($abilities as $ability) {
            if (in_array($ability, $gated, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param class-string $clause */
    private static function holds(QueryPlan $plan, string $clause): bool
    {
        foreach ($plan->clauses as $held) {
            if ($held instanceof $clause) {
                return true;
            }
        }

        return false;
    }

    private static function key(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * @param  list<Model>  $models
     * @return array<array-key, true>
     */
    private static function outside(Resource $resource, array $models, bool $withTrashed): array
    {
        $keys = [];

        foreach ($models as $row) {
            $key = $row->getKey();

            if (is_int($key) || is_string($key)) {
                $keys[$key] = $key;
            }
        }

        if ($keys === []) {
            return [];
        }

        $model = $resource::newModel();
        $query = $model->newQuery();

        if ($withTrashed) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        (new PerimeterClause($resource))->apply($query);

        $reached = KeysGrammar::idOf($resource)
            ->whereKeys($query, array_values($keys))
            ->pluck($model->getQualifiedKeyName())
            ->map(self::key(...))
            ->all();

        return array_fill_keys(array_diff(array_map(self::key(...), array_values($keys)), $reached), true);
    }
}
