<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use ArrayAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Search\Totals\TotalsBucket;
use Lomkit\Rest\Search\Totals\TotalsGroup;
use ReflectionMethod;

/** @internal */
final readonly class TurbopufferTotals
{
    public const int GROUPS = 10000;

    public function __construct(
        private TurbopufferEngine $engine,
        private EngineTotals $totals,
    ) {}

    /** @param Builder<Model> $builder */
    public function of(Builder $builder): SearchTotals
    {
        $filters = $this->engine->buildSearchParameters($builder, 1)['filters'] ?? null;
        $namespace = (new ReflectionMethod($this->engine, 'namespace'))->invoke($this->engine, $builder);
        $query = static fn (array $parameters): array => self::rows(is_object($namespace) && method_exists($namespace, 'query') ? $namespace->query(array_filter([...$parameters, 'filters' => $filters, 'consistency' => ['level' => 'strong']], static fn (mixed $value): bool => $value !== null)) : null);
        $aggregates = ['rest_count' => ['Count']];

        foreach ($this->totals->node->measures as $index => $measure) {
            $aggregates["rest_measure_{$index}"] = [self::function($measure->type), $measure->field->name];
        }

        $all = self::rows($query(['aggregate_by' => $aggregates])['aggregations'] ?? null);
        $count = is_numeric($all['rest_count'] ?? null) ? (int) $all['rest_count'] : 0;
        $raw = [];

        foreach ($this->totals->node->measures as $index => $measure) {
            $raw[$measure->key()] = $all["rest_measure_{$index}"] ?? null;
        }

        return new SearchTotals(
            $this->totals->node->figures($count, $raw),
            array_map(fn (GroupNode $group): TotalsGroup => $this->group($group, $query, $count), $this->totals->node->groups),
        );
    }

    /** @param Closure(array<string, mixed>): array<array-key, mixed> $query */
    private function group(GroupNode $group, Closure $query, int $count): TotalsGroup
    {
        $name = $group->field->name;
        $groups = self::rows($query(['aggregate_by' => ['rest_count' => ['Count']], 'group_by' => [$name], 'limit' => self::GROUPS])['aggregation_groups'] ?? null);

        if (count($groups) >= self::GROUPS) {
            throw EngineSearchException::cappedFacetValues($this->totals->resource, $name, self::GROUPS);
        }

        $buckets = [];

        foreach ($groups as $row) {
            $row = self::rows($row);
            $buckets[] = TotalsBucket::read($group->field, $row[$name] ?? null, is_numeric($row['rest_count'] ?? null) ? (int) $row['rest_count'] : 0);
        }

        return TotalsGroup::of($group, $buckets, $count);
    }

    private static function function(MeasureType $type): string
    {
        return ucfirst($type->value);
    }

    /** @return array<array-key, mixed> */
    private static function rows(mixed $value): array
    {
        return match (true) {
            is_array($value) => $value,
            $value instanceof ArrayAccess && method_exists($value, 'toArray') => (array) $value->toArray(),
            default => [],
        };
    }
}
