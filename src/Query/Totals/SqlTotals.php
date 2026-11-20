<?php

namespace Lomkit\Rest\Query\Totals;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Query\Expressions\NullsLast;
use Lomkit\Rest\Query\Expressions\TotalsSelect;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Search\Totals\TotalsBucket;
use Lomkit\Rest\Search\Totals\TotalsFigures;
use Lomkit\Rest\Search\Totals\TotalsGroup;
use Lomkit\Rest\Search\Totals\TotalsNode;

/** @internal */
final readonly class SqlTotals
{
    public function __construct(private TotalsNode $totals) {}

    /** @param Builder<Model> $query */
    public function of(Builder $query): SearchTotals
    {
        $grouped = array_map(static fn (GroupNode $group): string => $group->field->name, $this->totals->groups);
        $rows = TotalsRows::of($query, array_values(array_unique([...$this->totals->measuredNames(), ...$grouped])));

        return new SearchTotals(
            $this->all($rows),
            array_map(fn (GroupNode $group): TotalsGroup => $this->group($rows, $group), $this->totals->groups),
        );
    }

    private function all(TotalsRows $rows): TotalsFigures
    {
        $select = [TotalsSelect::count('rest_count')];

        foreach ($this->totals->measures as $index => $measure) {
            $select[] = TotalsSelect::measure($measure->type, $rows->columns[$measure->field->name], "rest_measure_{$index}");
        }

        $row = (array) $rows->query
            ->clone()
            ->select($select)
            ->first();
        $raw = [];

        foreach ($this->totals->measures as $index => $measure) {
            $raw[$measure->key()] = $row["rest_measure_{$index}"] ?? null;
        }

        return $this->totals->figures(self::integer($row['rest_count'] ?? 0), $raw);
    }

    private function group(TotalsRows $rows, GroupNode $group): TotalsGroup
    {
        $column = $rows->columns[$group->field->name];
        $counted = $rows->query
            ->clone()
            ->select([TotalsSelect::value($column, 'rest_value'), TotalsSelect::count('rest_count'), TotalsSelect::rows('rest_rows')])
            ->groupBy($column)
            ->orderBy(new NullsLast($column))
            ->orderByDesc('rest_count')
            ->orderBy($column)
            ->limit($group->limit + 1)
            ->get();
        $buckets = [];
        $total = 0;

        foreach ($counted as $row) {
            $row = (array) $row;
            $total = self::integer($row['rest_rows'] ?? 0);
            $buckets[] = TotalsBucket::read($group->field, $row['rest_value'] ?? null, self::integer($row['rest_count'] ?? 0));
        }

        return TotalsGroup::of($group, $buckets, $total);
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
