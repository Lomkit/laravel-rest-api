<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PageCountTest extends PackageTestCase
{
    private const string COUNT = 'select count(*) as aggregate from books';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach (range(1, 12) as $id) {
            Book::query()->create(['id' => $id, 'title' => 'Book '.$id]);
        }
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     * @param  list<string>  $queries
     */
    #[Test]
    #[DataProvider('pages')]
    public function it_counts_the_rows_only_when_the_page_does_not_give_the_total(array $search, array $ids, int $total, array $queries): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/books/search', ['search' => ['selects' => [['field' => 'id']], ...$search]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', $total);
        $this->assertSame($queries, array_map(fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog()));
    }

    /** @return array<string, array{array<string, mixed>, list<int>, int, list<string>}> */
    public static function pages(): array
    {
        return [
            'a page that is not full' => [['limit' => 25], range(12, 1), 12, ['select books.id from books order by books.id desc limit 25 offset 0']],
            'a full page' => [['limit' => 10], range(12, 3), 12, ['select books.id from books order by books.id desc limit 10 offset 0', self::COUNT]],
            'the last page' => [['limit' => 10, 'page' => 2], [2, 1], 12, ['select books.id from books order by books.id desc limit 10 offset 10']],
            'a page past the last one' => [['limit' => 10, 'page' => 3], [], 12, ['select books.id from books order by books.id desc limit 10 offset 20', self::COUNT]],
            'an empty first page' => [['filters' => [['field' => 'title', 'value' => 'Book 13']]], [], 0, ['select books.id from books where (books.title = ?) order by books.id desc limit 50 offset 0']],
        ];
    }
}
