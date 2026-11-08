<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Metrics\Grouping\GroupOrder;
use Lomkit\Rest\Metrics\Grouping\KeyFilter;

final readonly class RankedGroups
{
    /** @param list<array{key: int|string|bool|null, value: int|float|null}> $groups */
    public function __construct(
        public array $groups,
        private Measure $measure,
        private QueryBuilder $filtered,
        private MetricColumn $group,
    ) {}

    public static function largest(Measure $measure, QueryBuilder $filtered, MetricColumn $group, int $count): self
    {
        $base = self::grouped($measure, $filtered->clone(), $group);
        GroupOrder::byValue($base, $measure->rank('group'), $measure->nullable(), $group)->limit($count);

        return new self(self::read($measure, $base, $group), $measure, $filtered, $group);
    }

    /**
     * @param  list<int|string|bool|null>  $keys
     * @return list<array{key: int|string|bool|null, value: int|float|null}>
     */
    public static function within(Measure $measure, QueryBuilder $filtered, MetricColumn $group, array $keys): array
    {
        return self::read($measure, self::grouped($measure, KeyFilter::inside($filtered->clone(), $group, $keys), $group), $group);
    }

    /**
     * @param  list<int|string|bool|null>  $keys
     * @return list<array{key: int|string|bool|null, value: int|float|null}>
     */
    public static function firstOutside(Measure $measure, QueryBuilder $filtered, MetricColumn $group, array $keys, int $count): array
    {
        $base = self::grouped($measure, KeyFilter::outside($filtered->clone(), $group, $keys), $group);

        return self::read($measure, GroupOrder::byKey($base, $group)->limit($count), $group);
    }

    /** @param list<int|string|bool|null> $kept */
    public function others(array $kept): int|float|null
    {
        return $this->measure->single(KeyFilter::outside($this->filtered->clone(), $this->group, $kept));
    }

    private static function grouped(Measure $measure, QueryBuilder $filtered, MetricColumn $group): QueryBuilder
    {
        $base = $measure->rows($filtered, 'group', [$group->column]);
        $base->addSelect("{$group->column} as group_key");
        $measure->select($base, 'group');

        return $base->groupBy($group->column);
    }

    /** @return list<array{key: int|string|bool|null, value: int|float|null}> */
    private static function read(Measure $measure, QueryBuilder $base, MetricColumn $group): array
    {
        return array_values(array_map(function (object $row) use ($measure, $group): array {
            $values = get_object_vars($row);

            return ['key' => $group->key($values['group_key'] ?? null), 'value' => $measure->value($values, 'group')];
        }, $base->get()->all()));
    }
}
