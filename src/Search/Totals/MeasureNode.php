<?php

namespace Lomkit\Rest\Search\Totals;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;

/** @internal */
final readonly class MeasureNode
{
    public function __construct(
        public MeasureType $type,
        public Field $field,
    ) {}

    public function key(): string
    {
        return $this->type->value.'_'.$this->field->name;
    }

    public function value(mixed $raw): int|float|null
    {
        if (! is_numeric($raw)) {
            return $this->type === MeasureType::Sum ? $this->number(0) : null;
        }

        return $this->number($raw);
    }

    private function number(int|float|string $raw): int|float
    {
        if ($this->type === MeasureType::Avg || ! $this->field instanceof Integer) {
            return (float) $raw;
        }

        return is_int($raw) ? $raw : (int) round((float) $raw);
    }
}
