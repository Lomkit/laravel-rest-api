<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\CappedBookController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CappedTotalTest extends PackageTestCase
{
    private const string COUNT = 'select count(*) as aggregate from (select books.id from books limit 21) as capped';

    private const string GROUPED = 'from books inner join loans on loans.book_id = books.id group by books.id having count(loans.id) >= 2';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('capped-books', CappedBookController::class)->register();
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $envelope
     */
    #[Test]
    #[DataProvider('totals')]
    public function it_counts_the_rows_up_to_the_maximum_total(int $books, array $ids, array $envelope): void
    {
        $this->createBooks($books);

        $response = $this->search(['limit' => 10]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
        $this->assertSame($envelope, Arr::except($response->json(), 'data'));
    }

    /** @return array<string, array{int, list<int>, array<string, mixed>}> */
    public static function totals(): array
    {
        return [
            'no rows' => [0, [], ['current_page' => 1, 'from' => null, 'last_page' => 1, 'per_page' => 10, 'to' => null, 'total' => 0, 'total_capped' => false, 'meta' => []]],
            'fewer rows than the maximum' => [12, range(12, 3), ['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 10, 'to' => 10, 'total' => 12, 'total_capped' => false, 'meta' => []]],
            'as many rows as the maximum' => [20, range(20, 11), ['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 10, 'to' => 10, 'total' => 20, 'total_capped' => false, 'meta' => []]],
            'one row more than the maximum' => [21, range(21, 12), ['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 10, 'to' => 10, 'total' => 20, 'total_capped' => true, 'meta' => []]],
            'many rows more than the maximum' => [35, range(35, 26), ['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 10, 'to' => 10, 'total' => 20, 'total_capped' => true, 'meta' => []]],
        ];
    }

    #[Test]
    public function it_reads_at_most_one_row_past_the_maximum_to_count_a_page_in_the_middle(): void
    {
        $this->createBooks(35);
        DB::enableQueryLog();

        $response = $this->search(['limit' => 5, 'page' => 2]);

        $response->assertOk()->assertJsonPath('data.*.id', range(30, 26));
        $this->assertSame(['current_page' => 2, 'from' => 6, 'last_page' => 4, 'per_page' => 5, 'to' => 10, 'total' => 20, 'total_capped' => true, 'meta' => []], Arr::except($response->json(), 'data'));
        $this->assertSame(['select books.id from books order by books.id desc limit 5 offset 5', self::COUNT], self::loggedQueries());
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $envelope
     * @param  list<string>  $queries
     */
    #[Test]
    #[DataProvider('pagesPastTheMaximum')]
    public function it_answers_the_rows_of_a_page_past_the_maximum_total(int $page, array $ids, array $envelope, array $queries): void
    {
        $this->createBooks(35);
        DB::enableQueryLog();

        $response = $this->search(['limit' => 10, 'page' => $page]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
        $this->assertSame($envelope, Arr::except($response->json(), 'data'));
        $this->assertSame($queries, self::loggedQueries());
    }

    /** @return array<string, array{int, list<int>, array<string, mixed>, list<string>}> */
    public static function pagesPastTheMaximum(): array
    {
        return [
            'a full page' => [3, range(15, 6), ['current_page' => 3, 'from' => 21, 'last_page' => 3, 'per_page' => 10, 'to' => 30, 'total' => 30, 'total_capped' => true, 'meta' => []], ['select books.id from books order by books.id desc limit 10 offset 20', self::COUNT]],
            'the last page' => [4, range(5, 1), ['current_page' => 4, 'from' => 31, 'last_page' => 4, 'per_page' => 10, 'to' => 35, 'total' => 35, 'total_capped' => false, 'meta' => []], ['select books.id from books order by books.id desc limit 10 offset 30']],
            'an empty page' => [5, [], ['current_page' => 5, 'from' => null, 'last_page' => 2, 'per_page' => 10, 'to' => null, 'total' => 20, 'total_capped' => true, 'meta' => []], ['select books.id from books order by books.id desc limit 10 offset 40', self::COUNT]],
        ];
    }

    #[Test]
    #[DataProvider('groupedBooks')]
    public function it_counts_the_groups_of_a_grouping_scope_up_to_the_maximum_total(int $books, int $total, bool $capped): void
    {
        $this->createBooks($books + 3);
        Loan::query()->insert(array_merge(
            array_map(static fn (int $id): array => ['id' => $id, 'book_id' => intdiv($id + 1, 2), 'borrower' => 'Reader '.$id], range(1, $books * 2)),
            array_map(static fn (int $id): array => ['id' => $books * 2 + $id, 'book_id' => $books + $id, 'borrower' => 'Reader '.($books * 2 + $id)], range(1, 3)),
        ));
        DB::enableQueryLog();

        $response = $this->search(['limit' => 10, 'scopes' => [['name' => 'borrowedTwice']]]);

        $response->assertOk()->assertJsonPath('data.*.id', range($books, $books - 9));
        $this->assertSame(['last_page' => 2, 'total' => $total, 'total_capped' => $capped], Arr::only($response->json(), ['last_page', 'total', 'total_capped']));
        $this->assertSame([
            'select books.id '.self::GROUPED.' order by books.id desc limit 10 offset 0',
            'select count(*) as aggregate from (select books.id '.self::GROUPED.' limit 21) as capped',
        ], self::loggedQueries());
    }

    /** @return array<string, array{int, int, bool}> */
    public static function groupedBooks(): array
    {
        return [
            'fewer groups than the maximum' => [12, 12, false],
            'more groups than the maximum' => [25, 20, true],
        ];
    }

    #[Test]
    public function it_leaves_the_total_capped_key_out_of_the_pages_of_a_resource_without_a_maximum_total(): void
    {
        $this->createBooks(35);

        $response = $this->postJson('/api/books/search', ['search' => ['selects' => [['field' => 'id']], 'limit' => 10]]);

        $response->assertOk()->assertJsonPath('data.*.id', range(35, 26));
        $this->assertSame(['current_page' => 1, 'from' => 1, 'last_page' => 4, 'per_page' => 10, 'to' => 10, 'total' => 35, 'meta' => []], Arr::except($response->json(), 'data'));
    }

    private function createBooks(int $count): void
    {
        Book::query()->insert(array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Book '.$id], $count === 0 ? [] : range(1, $count)));
    }

    /** @param array<string, mixed> $search */
    private function search(array $search): TestResponse
    {
        return $this->postJson('/capped-books/search', ['search' => ['selects' => [['field' => 'id']], ...$search]]);
    }

    /** @return list<string> */
    private static function loggedQueries(): array
    {
        return array_map(static fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog());
    }
}
