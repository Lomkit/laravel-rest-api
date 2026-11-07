<?php

namespace Lomkit\Rest\Metrics;

use JsonSerializable;
use Lomkit\Rest\Exceptions\InvalidMetricException;

final readonly class MetricRange implements JsonSerializable
{
    public function __construct(
        public int $value,
        public string $label,
    ) {}

    /**
     * @param  array<mixed>  $ranges
     * @return list<self>
     */
    public static function listOf(array $ranges, string $metric): array
    {
        $declared = [];

        foreach ($ranges as $value => $label) {
            if (! is_int($value) || $value < 1 || ! is_string($label)) {
                throw InvalidMetricException::invalidRange($metric, (string) $value);
            }

            $declared[] = new self($value, $label);
        }

        return $declared;
    }

    /** @return array{value: int, label: string} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'label' => $this->label];
    }
}
