<?php

namespace Lomkit\Rest\Console\Generation;

enum MetricType: string
{
    case Value = 'value';
    case Trend = 'trend';
    case Partition = 'partition';
    case Progress = 'progress';
    case Table = 'table';
    case Custom = 'custom';

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    public static function list(): string
    {
        $names = array_column(self::cases(), 'value');
        $last = array_pop($names);

        return implode(', ', $names)." or {$last}";
    }

    public function label(): string
    {
        return match ($this) {
            self::Value => 'Value: one number',
            self::Trend => 'Trend: a series by day, week or month',
            self::Partition => 'Partition: one total per group',
            self::Progress => 'Progress: a number and its target',
            self::Table => 'Table: the top groups with their totals',
            self::Custom => 'Custom: a type and a result of your own',
        };
    }

    public function stub(): Stub
    {
        return match ($this) {
            self::Value => Stub::MetricValue,
            self::Trend => Stub::MetricTrend,
            self::Partition => Stub::MetricPartition,
            self::Progress => Stub::MetricProgress,
            self::Table => Stub::MetricTable,
            self::Custom => Stub::Metric,
        };
    }

    public function groups(): bool
    {
        return $this === self::Partition || $this === self::Table;
    }
}
