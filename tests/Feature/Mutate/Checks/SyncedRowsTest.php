<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use PHPUnit\Framework\Attributes\Test;

class SyncedRowsTest extends WriteChecksTestCase
{
    #[Test]
    public function it_toggles_against_the_rows_that_an_earlier_entry_attached(): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [
            ['operation' => 'attach', 'key' => [102]],
            ['operation' => 'toggle', 'key' => [101, 102]],
        ]]]]);

        $response->assertOk();
        $this->assertSame([
            'policy update Book(Dune) []',
            'policy view Shelf(Poetry)',
            'policy attachShelf Book(Dune) Shelf(Poetry)',
            'policy view Shelf(Classics)',
            'policy detachShelf Book(Dune) Shelf(Classics)',
            'policy detachShelf Book(Dune) Shelf(Poetry)',
        ], array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy '))));
        $this->assertSame(['Book(Dune) []', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'], self::rows('books'));
    }

    #[Test]
    public function it_detaches_a_row_that_an_earlier_entry_created_when_a_sync_leaves_it_out(): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Fresh']],
            ['operation' => 'sync', 'key' => [102]],
        ]]]]);

        $response->assertOk();
        $this->assertSame([
            'policy create',
            'policy update Book(Dune) []',
            'policy create Shelf* {"name":"Fresh"}',
            'policy view Shelf(Poetry)',
            'policy attachShelf Book(Dune) Shelf(Poetry)',
            'policy detachShelf Book(Dune) Shelf(Classics)',
            'policy attachShelf Book(Dune) Shelf(Fresh)',
            'policy detachShelf Book(Dune) Shelf(Fresh)',
        ], array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy '))));
        $this->assertSame(['Book(Dune) [Shelf(Poetry)]', 'Book(Emma) [Shelf(Drama), Shelf(Hidden), Shelf(Poetry)]'], self::rows('books'));
    }
}
