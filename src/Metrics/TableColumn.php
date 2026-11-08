<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Support\Str;

final readonly class TableColumn
{
    private function __construct(
        public Aggregate|Percentile $function,
        public ?string $field = null,
        public ?string $label = null,
    ) {}

    public static function count(): self
    {
        return new self(Aggregate::Count);
    }

    public static function sum(string $field): self
    {
        return new self(Aggregate::Sum, $field);
    }

    public static function average(string $field): self
    {
        return new self(Aggregate::Average, $field);
    }

    public static function min(string $field): self
    {
        return new self(Aggregate::Min, $field);
    }

    public static function max(string $field): self
    {
        return new self(Aggregate::Max, $field);
    }

    public static function median(string $field): self
    {
        return new self(Percentile::median(), $field);
    }

    public static function percentile(string $field, float $percentile): self
    {
        return new self(Percentile::of($percentile), $field);
    }

    public function label(string $label): self
    {
        return new self($this->function, $this->field, $label);
    }

    /** @return array{name: string, label: string, aggregate: string, percentile?: float} */
    public function describe(string $name): array
    {
        $column = ['name' => $name, 'label' => $this->label ?? Str::headline($name), 'aggregate' => $this->function->value];

        if (! $this->function instanceof Percentile || $this->function->value === Percentile::MEDIAN) {
            return $column;
        }

        return [...$column, 'percentile' => $this->function->fraction];
    }
}
