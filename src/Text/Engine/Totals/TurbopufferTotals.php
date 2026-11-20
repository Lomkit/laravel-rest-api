<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Search\Totals\TotalsBucket;
use Lomkit\Rest\Search\Totals\TotalsGroup;
use ReflectionMethod;

/** @internal */
final readonly class TurbopufferTotals
{
    public const int MAX_GROUPS = 9999;

    public const int AGGREGATES_PER_QUERY = 8;

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
        $all = $this->aggregations($query);

        if (! is_numeric($all['rest_count'] ?? null)) {
            throw EngineSearchException::missingCount($this->totals->resource, $this->totals->engine);
        }

        $count = (int) $all['rest_count'];
        $raw = [];

        foreach ($this->totals->node->measures as $index => $measure) {
            $raw[$measure->key()] = $this->totals->rounded($measure->field->name, $all["rest_measure_{$index}"] ?? null);
        }

        return new SearchTotals(
            $this->totals->node->figures($count, $raw),
            array_map(fn (GroupNode $group): TotalsGroup => $this->group($group, $query, $count), $this->totals->node->groups),
        );
    }

    /**
     * @param  Closure(array<string, mixed>): array<array-key, mixed>  $query
     * @return array<array-key, mixed>
     */
    private function aggregations(Closure $query): array
    {
        $functions = ['rest_count' => ['Count']];

        foreach ($this->totals->node->measures as $index => $measure) {
            $functions["rest_measure_{$index}"] = [ucfirst($measure->type->value), $measure->field->name];
        }

        $aggregations = [];

        foreach (array_chunk($functions, self::AGGREGATES_PER_QUERY, true) as $batch) {
            $aggregations = [...$aggregations, ...self::rows($query(['aggregate_by' => $batch])['aggregations'] ?? null)];
        }

        return $aggregations;
    }

    /** @param Closure(array<string, mixed>): array<array-key, mixed> $query */
    private function group(GroupNode $group, Closure $query, int $count): TotalsGroup
    {
        $name = $group->field->name;
        $groups = self::rows($query(['aggregate_by' => ['rest_count' => ['Count']], 'group_by' => [$name], 'limit' => self::MAX_GROUPS])['aggregation_groups'] ?? null);

        if (count($groups) >= self::MAX_GROUPS) {
            throw EngineSearchException::cappedFacetValues($this->totals->resource, $name, self::MAX_GROUPS);
        }

        $buckets = [];
        $counted = 0;

        foreach ($groups as $row) {
            $row = self::rows($row);
            $rows = is_numeric($row['rest_count'] ?? null) ? (int) $row['rest_count'] : 0;
            $counted += $rows;
            $buckets[] = TotalsBucket::read($group->field, $row[$name] ?? null, $rows);
        }

        if ($counted > $count) {
            throw EngineSearchException::multiValuedFacet($this->totals->resource, $name);
        }

        return TotalsGroup::of($group, $buckets, $count);
    }

    /** @return array<array-key, mixed> */
    private static function rows(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
