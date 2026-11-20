<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\MeasureNode;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Search\Totals\TotalsBucket;
use Lomkit\Rest\Search\Totals\TotalsGroup;

use function Illuminate\Support\enum_value;

/** @internal */
final readonly class RowTotals
{
    public function __construct(private EngineTotals $totals) {}

    /** @param list<Model> $rows */
    public function of(array $rows): SearchTotals
    {
        $measures = [];

        foreach ($this->totals->node->measures as $measure) {
            $measures[$measure->key()] = $this->totals->rounded($measure->field->name, self::measure($measure, self::numbers($rows, $measure->field->name)));
        }

        return new SearchTotals(
            $this->totals->node->figures(count($rows), $measures),
            array_map(static fn (GroupNode $group): TotalsGroup => self::group($group, $rows), $this->totals->node->groups),
        );
    }

    /** @param list<int|float> $values */
    private static function measure(MeasureNode $measure, array $values): int|float|null
    {
        if ($values === []) {
            return null;
        }

        return match ($measure->type) {
            MeasureType::Sum => array_sum($values),
            MeasureType::Avg => array_sum($values) / count($values),
            MeasureType::Min => min($values),
            MeasureType::Max => max($values),
        };
    }

    /**
     * @param  list<Model>  $rows
     * @return list<int|float>
     */
    private static function numbers(array $rows, string $name): array
    {
        $numbers = [];

        foreach ($rows as $row) {
            $value = $row->getAttribute($name);

            if (is_numeric($value)) {
                $numbers[] = $value + 0;
            }
        }

        return $numbers;
    }

    /** @param list<Model> $rows */
    private static function group(GroupNode $group, array $rows): TotalsGroup
    {
        $counts = [];

        foreach ($rows as $row) {
            $value = enum_value($row->getAttribute($group->field->name));

            if (is_int($value) || is_string($value) || is_bool($value)) {
                $key = get_debug_type($value).':'.var_export($value, true);
                $counts[$key] = [$value, ($counts[$key][1] ?? 0) + 1];
            }
        }

        return TotalsGroup::of($group, array_values(array_map(static fn (array $count): TotalsBucket => TotalsBucket::read($group->field, $count[0], $count[1]), $counts)), count($rows));
    }
}
