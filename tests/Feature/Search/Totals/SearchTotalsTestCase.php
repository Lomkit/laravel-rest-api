<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Festival;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class SearchTotalsTestCase extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Festival];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        DB::table('shows')->insert([
            self::show(1, 'Jazz', 'Great', '10.50', 100, true, 'on_sale', 1),
            self::show(2, 'Jazz', 'Great', '20.25', 200, false, 'on_sale', 2),
            self::show(3, 'Rock', null, null, 50, false, 'planned', 1),
            self::show(4, 'Opening', null, '5.00', 300, true, 'sold_out', 3),
            self::show(5, 'Folk', 'Calm', '7.25', 100, false, 'on_sale', 1),
            [...self::show(6, 'Gone', 'Great', '99.00', 999, true, 'on_sale', 1), 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
    }

    /** @param array<string, mixed> $search */
    protected function search(array $search, string $resource = 'shows'): TestResponse
    {
        return $this->postJson("/api/{$resource}/search", ['search' => $search]);
    }

    /** @return array<string, mixed> */
    private static function show(int $id, string $title, ?string $summary, ?string $price, int $seats, bool $featured, string $status, int $level): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'summary' => $summary,
            'price' => $price,
            'seats' => $seats,
            'featured' => $featured,
            'status' => $status,
            'level' => $level,
            'starts_on' => '2026-07-01',
            'deleted_at' => null,
        ];
    }
}
