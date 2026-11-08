<?php

namespace Lomkit\Rest\Metrics;

final readonly class PartialPeriods implements PeriodValues
{
    /** @param list<array{period: string, key: int|string|bool|null, partials: list<int|float|null>}> $rows */
    public function __construct(
        private Aggregate $function,
        private bool $integer,
        private array $rows,
    ) {}

    #[\Override]
    public function empty(): int|float|null
    {
        return $this->function->value($this->function->partials([null, null], $this->integer));
    }

    #[\Override]
    public function series(): array
    {
        return $this->merged([]);
    }

    #[\Override]
    public function cells(MetricColumn $split): array
    {
        return array_map(fn (array $row): array => ['period' => $row['period'], 'key' => $row['key'], 'value' => $this->function->value($row['partials'])], $this->rows);
    }

    #[\Override]
    public function groups(MetricColumn $split): GroupValues
    {
        $keys = [];
        $totals = [];

        foreach ($this->rows as $row) {
            $index = array_search($row['key'], $keys, true);

            if ($index === false) {
                $keys[] = $row['key'];
                $totals[] = $row['partials'];

                continue;
            }

            $totals[$index] = $this->function->merge($totals[$index], $row['partials']);
        }

        return new PartialGroups($this->function, $this->integer, array_map(fn (int|string|bool|null $key, array $partials): array => ['key' => $key, 'partials' => $partials], $keys, $totals));
    }

    #[\Override]
    public function others(MetricColumn $split, array $kept): array
    {
        return $this->merged($kept);
    }

    /**
     * @param  list<int|string|bool|null>  $kept
     * @return array<string, int|float|null>
     */
    private function merged(array $kept): array
    {
        $partials = [];

        foreach ($this->rows as $row) {
            if (in_array($row['key'], $kept, true)) {
                continue;
            }

            $partials[$row['period']] = isset($partials[$row['period']]) ? $this->function->merge($partials[$row['period']], $row['partials']) : $row['partials'];
        }

        return array_map($this->function->value(...), $partials);
    }
}
