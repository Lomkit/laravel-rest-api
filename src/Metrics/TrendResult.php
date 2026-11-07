<?php

namespace Lomkit\Rest\Metrics;

final readonly class TrendResult implements MetricResult
{
    /**
     * @param  list<TrendPoint>  $series
     * @param  list<TrendSplit>|null  $splits
     */
    public function __construct(
        public array $series,
        public ?array $splits = null,
        public ?TrendSplit $other = null,
    ) {}

    /** @param list<array{period: string, key: int|string|bool|null, partials: list<int|float|null>}> $rows */
    public static function of(Aggregate $function, bool $integer, Periods $periods, ?MetricColumn $split, array $rows, ?int $limit): self
    {
        $empty = $function->value($function->partials([null, null], $integer));
        $series = self::series($function, $periods, $rows, $empty);

        if ($split === null) {
            return new self($series);
        }

        $kept = array_map(fn (PartitionPart $part): int|string|bool|null => $part->key, PartitionResult::of($function, $split, self::totals($function, $rows), $limit)->parts);
        usort($kept, fn (int|string|bool|null $one, int|string|bool|null $two): int => [$one === null, $one] <=> [$two === null, $two]);
        $splits = array_map(fn (int|string|bool|null $key): TrendSplit => new TrendSplit($key, $split->label($key), self::series($function, $periods, array_filter($rows, fn (array $row): bool => $row['key'] === $key), $empty)), $kept);
        $folded = array_filter($rows, fn (array $row): bool => ! in_array($row['key'], $kept, true));

        return new self($series, $splits, $folded === [] ? null : new TrendSplit(null, PartitionResult::OTHER, self::series($function, $periods, $folded, $empty)));
    }

    /** @param array<int|string, string> $labels */
    public function labels(array $labels, ?string $other = null): self
    {
        $splits = $this->splits === null ? null : array_map(fn (TrendSplit $split): TrendSplit => $split->labelled(self::label($labels, $split)), $this->splits);

        return new self($this->series, $splits, $other === null ? $this->other : $this->other?->labelled($other));
    }

    /** @return array{series: list<TrendPoint>, splits?: list<TrendSplit>, other?: array{label: string|null, series: list<TrendPoint>}|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        if ($this->splits === null) {
            return ['series' => $this->series];
        }

        $other = $this->other === null ? null : ['label' => $this->other->label, 'series' => $this->other->series];

        return ['series' => $this->series, 'splits' => $this->splits, 'other' => $other];
    }

    /**
     * @param  array<array{period: string, key: int|string|bool|null, partials: list<int|float|null>}>  $rows
     * @return list<TrendPoint>
     */
    private static function series(Aggregate $function, Periods $periods, array $rows, int|float|null $empty): array
    {
        $partials = [];

        foreach ($rows as $row) {
            $partials[$row['period']] = isset($partials[$row['period']]) ? $function->merge($partials[$row['period']], $row['partials']) : $row['partials'];
        }

        return array_map(fn (string $label): TrendPoint => new TrendPoint($label, isset($partials[$label]) ? $function->value($partials[$label]) : $empty), $periods->labels());
    }

    /**
     * @param  list<array{period: string, key: int|string|bool|null, partials: list<int|float|null>}>  $rows
     * @return list<array{key: int|string|bool|null, partials: list<int|float|null>}>
     */
    private static function totals(Aggregate $function, array $rows): array
    {
        $keys = [];
        $totals = [];

        foreach ($rows as $row) {
            $index = array_search($row['key'], $keys, true);

            if ($index === false) {
                $keys[] = $row['key'];
                $totals[] = $row['partials'];

                continue;
            }

            $totals[$index] = $function->merge($totals[$index], $row['partials']);
        }

        return array_map(fn (int|string|bool|null $key, array $partials): array => ['key' => $key, 'partials' => $partials], $keys, $totals);
    }

    /** @param array<int|string, string> $labels */
    private static function label(array $labels, TrendSplit $split): ?string
    {
        $key = is_bool($split->key) ? (int) $split->key : $split->key;

        return $key !== null && array_key_exists($key, $labels) ? $labels[$key] : $split->label;
    }
}
