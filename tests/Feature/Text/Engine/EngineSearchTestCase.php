<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;

abstract class EngineSearchTestCase extends EngineTestCase
{
    private const array ROWS = [
        1 => ['id' => 1, 'code' => 'V-c', 'number' => 3, 'team' => 'red', 'title' => 'Report alpha', 'price' => 1.5, 'active' => true],
        2 => ['id' => 2, 'code' => 'V-b', 'number' => 2, 'team' => 'red', 'title' => 'Budget', 'price' => 30.0, 'active' => true],
        3 => ['id' => 3, 'code' => 'V-a', 'number' => 1, 'team' => 'red', 'title' => 'Report beta', 'price' => 7.25, 'active' => true],
        4 => ['id' => 4, 'code' => 'V-d', 'number' => 4, 'team' => 'blue', 'title' => 'Report blue', 'price' => 12.0, 'active' => true],
        5 => ['id' => 5, 'code' => 'V-e', 'number' => 5, 'team' => 'red', 'title' => 'Report inactive', 'price' => 40.0, 'active' => false],
        6 => ['id' => 6, 'code' => 'V-f', 'number' => 6, 'team' => 'red', 'title' => 'Report trashed', 'price' => 5.0, 'active' => true],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLedger();
    }

    protected function seedLedger(): void
    {
        Ledger::seed();
    }

    /** @param array<string, mixed> $search */
    protected function search(string $resource, array $search): TestResponse
    {
        return $this->postJson("/api/{$resource}/search", ['search' => $search]);
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    protected function page(array $ids, int $total, int $perPage = 50, int $page = 1): array
    {
        $first = ($page - 1) * $perPage + 1;

        return [
            'current_page' => $page,
            'data' => array_map(fn (int $id): array => self::ROWS[$id], $ids),
            'from' => $ids === [] ? null : $first,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'to' => $ids === [] ? null : $first + count($ids) - 1,
            'total' => $total,
            'meta' => [],
        ];
    }
}
