<?php

namespace Lomkit\Rest\Metrics;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Metrics\Grouping\PeriodGrammar;
use Lomkit\Rest\Metrics\Grouping\PeriodLabel;
use Lomkit\Rest\Metrics\Grouping\ZoneShift;

final readonly class Periods
{
    private function __construct(
        public PeriodUnit $unit,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        private MetricColumn $date,
        private Model $model,
        private int $weekStartsOn,
    ) {}

    /** @param Builder<Model> $query */
    public static function last(Builder $query, MetricColumn $date, PeriodUnit $unit, int $count, DateTimeZone $zone, int $weekStartsOn): self
    {
        $to = Date::now()
            ->toImmutable()
            ->setTimezone($zone);

        return new self($unit, $unit->add($unit->start($to, $weekStartsOn), 1 - $count), $to, $date, $query->getModel(), $weekStartsOn);
    }

    /** @return list<string> */
    public function labels(): array
    {
        $labels = [];

        for ($start = $this->from; $start <= $this->to; $start = $this->unit->add($start, 1)) {
            $labels[] = $this->unit->label($start);
        }

        return $labels;
    }

    public function label(): PeriodLabel
    {
        $shift = $this->date->field instanceof DateTime ? ZoneShift::between($this->from, $this->to, self::storedZone(), $this->model) : null;

        return new PeriodLabel(PeriodGrammar::for($this->model->getConnection()->getDriverName(), $this->weekStartsOn), $this->unit, $this->date->column, $shift);
    }

    /** @param Builder<Model> $query */
    public function group(Builder $query): QueryBuilder
    {
        $label = $this->label();

        return $this->window($query)
            ->select([$label->as('period')])
            ->groupBy([$label]);
    }

    /** @param Builder<Model> $query */
    public function window(Builder $query): QueryBuilder
    {
        return $query->clone()
            ->toBase()
            ->reorder()
            ->select([])
            ->whereBetween($this->date->column, [$this->stored($this->from), $this->stored($this->to)]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<TrendPoint>
     */
    public function series(array $values, int|float|null $empty = null): array
    {
        return array_map(fn (string $label): TrendPoint => new TrendPoint($label, self::number($values[$label] ?? null) ?? $empty), $this->labels());
    }

    private function stored(CarbonImmutable $date): string
    {
        $date = $this->date->field instanceof DateTime ? $date->setTimezone(self::storedZone()) : CarbonImmutable::parse($date->toDateString(), self::storedZone());

        return (string) $this->model->fromDateTime($date);
    }

    private static function storedZone(): DateTimeZone
    {
        return new DateTimeZone(Config::string('app.timezone'));
    }

    private static function number(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : (is_numeric($value) ? $value + 0 : null);
    }
}
