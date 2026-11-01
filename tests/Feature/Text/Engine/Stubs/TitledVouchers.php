<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;

final class TitledVouchers
{
    public const string QUOTED = 'Report "quoted"';

    public const string BACKSLASH = 'Report back\slash';

    public const string TRAILING = 'Report trailing\\';

    public const string COMMA = 'Report, comma';

    public const string BRACKETS = 'Report [bracket]';

    private const array VOUCHERS = [
        [7, 'V-g', 7, 'red', self::QUOTED, 2],
        [8, 'V-h', 8, 'red', self::BACKSLASH, 3],
        [9, 'V-i', 9, 'red', self::TRAILING, 4],
        [10, 'V-j', 10, 'green', 'Report green', 9],
        [11, 'V-k', 11, 'red', self::COMMA, 5.5],
        [12, 'V-l', 12, 'red', self::BRACKETS, 6],
    ];

    public static function seed(): void
    {
        foreach (self::VOUCHERS as [$id, $code, $number, $team, $title, $price]) {
            Voucher::query()->create(['id' => $id, 'code' => $code, 'number' => $number, 'team' => $team, 'title' => $title, 'price' => $price, 'active' => true]);
        }
    }
}
