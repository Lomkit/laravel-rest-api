<?php

namespace Lomkit\Rest\Metrics;

interface PeriodValues
{
    public function empty(): int|float|null;

    /** @return array<string, int|float|null> */
    public function series(): array;

    /** @return list<array{period: string, key: int|string|bool|null, value: int|float|null}> */
    public function cells(MetricColumn $split): array;

    public function groups(MetricColumn $split): GroupValues;

    /**
     * @param  list<int|string|bool|null>  $kept
     * @return array<string, int|float|null>
     */
    public function others(MetricColumn $split, array $kept): array;
}
