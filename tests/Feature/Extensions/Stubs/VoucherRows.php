<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;

final class VoucherRows
{
    /** @param array<array-key, mixed> $rows */
    public static function describe(array $rows): string
    {
        return implode(',', array_map(fn (mixed $row): string => $row instanceof Model ? $row->getKey().' '.$row->getAttribute('team') : '?', $rows));
    }
}
