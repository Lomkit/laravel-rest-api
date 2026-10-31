<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Fixture;

class Ledger implements Fixture
{
    private const array VOUCHERS = [
        [1, 'V-c', 3, 'red', 'Report alpha', 1.5, true],
        [2, 'V-b', 2, 'red', 'Budget', 30, true],
        [3, 'V-a', 1, 'red', 'Report beta', 7.25, true],
        [4, 'V-d', 4, 'blue', 'Report blue', 12, true],
        [5, 'V-e', 5, 'red', 'Report inactive', 40, false],
        [6, 'V-f', 6, 'red', 'Report trashed', 5, true],
    ];

    private const array LINES = [[1, 1, 'l1a'], [2, 1, 'l1b'], [3, 3, 'l3a'], [4, 5, 'l5a'], [5, 4, 'l4a']];

    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function routes(): void
    {
        Route::prefix('api')->group(function (): void {
            Rest::resource('vouchers', VoucherController::class)->withSoftDeletes();
            Rest::resource('numbered-vouchers', NumberedVoucherController::class)->withSoftDeletes();
            Rest::resource('scoped-vouchers', ScopedVoucherController::class)->withSoftDeletes();
            Rest::resource('team-vouchers', TeamVoucherController::class)->withSoftDeletes();
            Rest::resource('voucher-lines', VoucherLineController::class);
        });
    }

    public static function seed(): void
    {
        foreach (self::VOUCHERS as [$id, $code, $number, $team, $title, $price, $active]) {
            Voucher::query()->create(['id' => $id, 'code' => $code, 'number' => $number, 'team' => $team, 'title' => $title, 'price' => $price, 'active' => $active]);
        }

        Voucher::query()
            ->findOrFail(6)
            ->delete();

        foreach (self::LINES as [$id, $voucher, $label]) {
            VoucherLine::query()->create(['id' => $id, 'voucher_id' => $voucher, 'label' => $label]);
        }
    }
}
