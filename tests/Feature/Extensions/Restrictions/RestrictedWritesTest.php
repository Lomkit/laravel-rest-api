<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingBookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [HiddenRowsExtension::class])]
class RestrictedWritesTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;
    use ResetsExtensionStubs;
    use SeedsLibraryRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLibrary();
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('hiddenKeys')]
    public function it_refuses_a_key_that_a_restriction_hides(string $uri, array $entry, string $key): void
    {
        $rows = self::library();

        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame($rows, self::library());
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function hiddenKeys(): array
    {
        return [
            'a root key' => ['/api/books/mutate', ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Persuasion']], 'mutate.0.key'],
            'a has one of many key' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['latestLoan' => ['operation' => 'attach', 'key' => 104]]], 'mutate.0.relations.latestLoan.key'],
            'a belongs to key' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 103, 'relations' => ['book' => ['operation' => 'attach', 'key' => 103]]], 'mutate.0.relations.book.key'],
            'a morph to key' => ['/api/labels/mutate', ['operation' => 'update', 'key' => 103, 'relations' => ['labelable' => ['operation' => 'attach', 'type' => Book::class, 'key' => 103]]], 'mutate.0.relations.labelable.key'],
            'a belongs to many attach key' => ['/api/books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [103]]]]], 'mutate.0.relations.shelves.0.key'],
            'a belongs to many detach key' => ['/api/books/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'detach', 'key' => [103]]]]], 'mutate.0.relations.shelves.0.key'],
        ];
    }

    #[Test]
    public function it_reads_the_rows_of_each_resource_through_its_restriction(): void
    {
        $this->captureQueries();

        $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [102]]]]],
        ]])->assertOk();

        $this->assertSame([
            ['select * from books where ((books.title != ?)) and books.id in (101, 102)', ['Hidden']],
            ['select * from loans where ((loans.borrower != ?)) and loans.id in (101)', ['Hidden']],
            ['select * from shelves where ((shelves.name != ?)) and shelves.id in (102)', ['Hidden']],
            ['select shelf_id from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)', [102, 102]],
            ['select shelf_id from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)', [102, 102]],
        ], array_values(array_filter($this->statements, fn (array $statement): bool => str_starts_with($statement[0], 'select'))));
    }

    #[Test]
    public function it_leaves_out_of_a_pivot_sync_the_attached_rows_that_a_restriction_hides(): void
    {
        $this->captureQueries();

        $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'sync', 'key' => [102]]]]],
        ]])->assertOk();

        $this->assertSame([[101, 103], [101, 102]], DB::table('book_shelf')->orderBy('shelf_id', 'desc')->get(['book_id', 'shelf_id'])->map(fn (object $row): array => [(int) $row->book_id, (int) $row->shelf_id])->all());
        $this->assertSame([['select shelves.*, book_shelf.book_id as pivot_book_id, book_shelf.shelf_id as pivot_shelf_id from shelves inner join book_shelf on shelves.id = book_shelf.shelf_id where book_shelf.book_id = ? and ((shelves.name != ?)) limit 1002', [101, 'Hidden']]], array_values(array_filter($this->readsOf('shelves'), fn (array $statement): bool => str_contains($statement[0], 'inner join'))));
    }

    #[Test]
    public function it_leaves_out_of_a_sync_the_children_that_a_restriction_hides(): void
    {
        $this->app?->bind(BookResource::class, SyncingBookResource::class);
        Label::query()->insert(['id' => 105, 'labelable_type' => Book::class, 'labelable_id' => 101, 'text' => 'Hidden']);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'sync', 'key' => [101]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([101 => 101, 102 => 102, 103 => null, 104 => 101, 105 => 101], Label::query()
            ->orderBy('id')
            ->pluck('labelable_id', 'id')
            ->all());
    }

    #[Test]
    public function it_returns_null_for_a_written_record_that_the_restriction_hides(): void
    {
        $this->postJson('/api/books/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Hidden']],
                ['operation' => 'create', 'attributes' => ['title' => 'Persuasion']],
            ],
            'response' => ['selects' => [['field' => 'title']]],
        ])
            ->assertOk()
            ->assertJsonPath('records', [null, ['title' => 'Persuasion']]);
    }

    #[Test]
    public function it_gives_each_lookup_the_resource_that_it_restricts(): void
    {
        $this->postJson('/api/loans/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'attach', 'key' => 102]]],
        ]])->assertOk();

        $this->assertSame(['LoanResource guest', 'BookResource guest'], RestrictionLog::$seen);
        $this->assertSame(102, DB::table('loans')->where('id', 101)->value('book_id'));
    }
}
