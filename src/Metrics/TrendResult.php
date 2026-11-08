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

    public static function of(MeasurePeriods $values, Periods $periods, ?MetricColumn $split, int $limit): self
    {
        $empty = $values->empty();
        $series = self::series($periods, $values->series(), $empty);

        if ($split === null) {
            return new self($series);
        }

        $parts = PartitionResult::ranked($values->largest($split, $limit + 1), $split);
        $kept = array_map(fn (PartitionPart $part): int|string|bool|null => $part->key, array_slice($parts, 0, $limit));
        usort($kept, fn (int|string|bool|null $one, int|string|bool|null $two): int => [$one === null, $one] <=> [$two === null, $two]);
        $folded = count($kept) < count($parts);
        $cells = $values->cells($split, $folded ? $kept : null);
        $splits = array_map(fn (int|string|bool|null $key): TrendSplit => new TrendSplit($key, $split->label($key), self::series($periods, self::keyed($cells, $key), $empty)), $kept);

        if (! $folded) {
            return new self($series, $splits);
        }

        return new self($series, $splits, new TrendSplit(null, PartitionResult::OTHER, self::series($periods, $values->others($split, $kept), $empty)));
    }

    /** @param array<int|string, string> $labels */
    public function labels(array $labels, ?string $other = null): self
    {
        $renamed = new KeyLabels($labels);
        $splits = $this->splits === null ? null : array_map(fn (TrendSplit $split): TrendSplit => $split->labelled($renamed->for($split->key, $split->label)), $this->splits);

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
     * @param  array<string, int|float|null>  $values
     * @return list<TrendPoint>
     */
    private static function series(Periods $periods, array $values, int|float|null $empty): array
    {
        return array_map(fn (string $label): TrendPoint => new TrendPoint($label, array_key_exists($label, $values) ? $values[$label] : $empty), $periods->labels());
    }

    /**
     * @param  list<array{period: string, key: int|string|bool|null, value: int|float|null}>  $cells
     * @return array<string, int|float|null>
     */
    private static function keyed(array $cells, int|string|bool|null $key): array
    {
        return array_column(array_filter($cells, fn (array $cell): bool => $cell['key'] === $key), 'value', 'period');
    }
}
