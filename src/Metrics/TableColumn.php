<?php

namespace Lomkit\Rest\Metrics;

final readonly class TableColumn
{
    private function __construct(
        public Aggregate $function,
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

    public function label(string $label): self
    {
        return new self($this->function, $this->field, $label);
    }
}
