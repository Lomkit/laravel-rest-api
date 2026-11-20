<?php

namespace Lomkit\Rest\Search\Totals;

/** @internal */
enum MeasureType: string
{
    case Sum = 'sum';
    case Avg = 'avg';
    case Min = 'min';
    case Max = 'max';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
