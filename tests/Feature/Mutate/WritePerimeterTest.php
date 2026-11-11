<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterBookController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterLabelController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterLoanController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\WidePerimeterBookController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class WritePerimeterTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;
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

        Rest::resource('perimeter-books', PerimeterBookController::class)->register();
        Rest::resource('perimeter-loans', PerimeterLoanController::class)->register();
        Rest::resource('perimeter-labels', PerimeterLabelController::class)->register();
        Rest::resource('wide-perimeter-books', WidePerimeterBookController::class)->register();
        $this->seedLibrary();
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('hiddenKeys')]
    public function it_refuses_a_key_outside_the_perimeter(string $uri, array $entry, string $key): void
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
            'a root key' => ['/perimeter-books/mutate', ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Persuasion']], 'mutate.0.key'],
            'a has one of many key' => ['/perimeter-books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['latestLoan' => ['operation' => 'attach', 'key' => 104]]], 'mutate.0.relations.latestLoan.key'],
            'a belongs to key' => ['/perimeter-loans/mutate', ['operation' => 'update', 'key' => 103, 'relations' => ['book' => ['operation' => 'attach', 'key' => 103]]], 'mutate.0.relations.book.key'],
            'a morph to key' => ['/perimeter-labels/mutate', ['operation' => 'update', 'key' => 103, 'relations' => ['labelable' => ['operation' => 'attach', 'type' => Book::class, 'key' => 103]]], 'mutate.0.relations.labelable.key'],
            'a belongs to many attach key' => ['/perimeter-books/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [103]]]]], 'mutate.0.relations.shelves.0.key'],
            'a belongs to many detach key' => ['/perimeter-books/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'detach', 'key' => [103]]]]], 'mutate.0.relations.shelves.0.key'],
        ];
    }

    #[Test]
    public function it_reads_the_rows_of_each_resource_through_its_perimeter(): void
    {
        $this->captureQueries();

        $this->postJson('/perimeter-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [102]]]]],
        ]])->assertOk();

        $this->assertSame([
            ['select * from books where (books.title <> ?) and books.id in (101, 102)', ['Hidden']],
            ['select * from loans where (loans.borrower <> ?) and loans.id in (101)', ['Hidden']],
            ['select * from shelves where (shelves.name <> ?) and shelves.id in (102)', ['Hidden']],
            ['select shelf_id from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)', [102, 102]],
            ['select shelf_id from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)', [102, 102]],
        ], array_values(array_filter($this->statements, fn (array $statement): bool => str_starts_with($statement[0], 'select'))));
    }

    #[Test]
    public function it_writes_a_row_that_the_perimeter_leaves_out_on_other_endpoints_only(): void
    {
        $this->postJson('/wide-perimeter-books/search', ['search' => ['selects' => [['field' => 'id']]]])->assertJsonPath('data.*.id', [107, 106, 102, 101]);

        $response = $this->postJson('/wide-perimeter-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Persuasion']],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 103]]]);
        $title = Book::query()
            ->findOrFail(103)
            ->getAttribute('title');
        $this->assertSame('Persuasion', $title);
    }
}
