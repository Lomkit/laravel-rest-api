<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentationLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ClonedCountBookController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CopiesExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ShortCountBookController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UncappedGroupedBookController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\CappedBookController;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [CopiesExtension::class])]
class AugmentedCountTest extends PackageTestCase
{
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

        AugmentationLog::reset();
        Rest::resource('capped-books', CappedBookController::class)->register();
        Rest::resource('grouped-books', UncappedGroupedBookController::class)->register();
        Rest::resource('short-count-books', ShortCountBookController::class)->register();
        Rest::resource('cloned-count-books', ClonedCountBookController::class)->register();
        Book::query()->insert(array_map(static fn (int $id): array => ['id' => $id, 'title' => $id === 1 ? 'Dune' : 'Book '.$id], range(1, 30)));
    }

    #[\Override]
    protected function tearDown(): void
    {
        AugmentationLog::reset();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('fullPages')]
    public function it_counts_a_full_page_without_the_augmentation(string $uri, string $count): void
    {
        DB::enableQueryLog();

        $this->postJson($uri, ['search' => ['selects' => [['field' => 'id']], 'limit' => 10]])->assertOk();

        $this->assertSame($count, self::loggedQueries()[1]);
    }

    /** @return array<string, array{string, string}> */
    public static function fullPages(): array
    {
        return [
            'without a maximum total' => ['/api/books/search', 'select count(*) as aggregate from books'],
            'with a maximum total' => ['/capped-books/search', 'select count(*) as aggregate from (select books.id from books limit 21) as capped'],
        ];
    }

    #[Test]
    #[DataProvider('groupedPages')]
    public function it_counts_the_groups_of_a_grouping_scope_without_the_augmentation(string $uri, string $count): void
    {
        Loan::query()->insert(array_map(static fn (int $id): array => ['id' => $id, 'book_id' => intdiv($id + 1, 2), 'borrower' => 'Reader '.$id], range(1, 24)));
        DB::enableQueryLog();

        $response = $this->postJson($uri, ['search' => ['selects' => [['field' => 'id']], 'limit' => 10, 'scopes' => [['name' => 'borrowedTwice']]]]);

        $response->assertOk()->assertJsonPath('total', 12);
        $this->assertSame([
            'select books.id, (select count(*) from loans where loans.book_id = books.id) as loans__copies '.self::GROUPED.' order by books.id desc limit 10 offset 0',
            $count,
        ], self::loggedQueries());
    }

    /** @return array<string, array{string, string}> */
    public static function groupedPages(): array
    {
        return [
            'without a maximum total' => ['/grouped-books/search', 'select count(*) as aggregate from (select books.id '.self::GROUPED.') as aggregate_table'],
            'with a maximum total' => ['/capped-books/search', 'select count(*) as aggregate from (select books.id '.self::GROUPED.' limit 21) as capped'],
        ];
    }

    #[Test]
    public function it_counts_the_wheres_that_an_override_adds_without_the_augmentation(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/short-count-books/search', ['search' => ['selects' => [['field' => 'id']], 'limit' => 10]]);

        $response->assertOk()->assertJsonPath('total', 29);
        $this->assertSame([
            'select books.id, (select count(*) from loans where loans.book_id = books.id) as loans__copies from books where title != ? order by books.id desc limit 10 offset 0',
            'select count(*) as aggregate from books where title != ?',
        ], self::loggedQueries());
    }

    #[Test]
    public function it_counts_the_groups_of_a_cloned_query_without_the_augmentation(): void
    {
        Loan::query()->insert(array_map(static fn (int $id): array => ['id' => $id, 'book_id' => intdiv($id + 1, 2), 'borrower' => 'Reader '.$id], range(1, 24)));
        DB::enableQueryLog();

        $response = $this->postJson('/cloned-count-books/search', ['search' => ['selects' => [['field' => 'id']], 'limit' => 10, 'scopes' => [['name' => 'borrowedTwice']]]]);

        $response->assertOk()->assertJsonPath('total', 12);
        $grouped = 'from books inner join loans on loans.book_id = books.id where books.id > ? group by books.id having count(loans.id) >= 2';
        $this->assertSame([
            'select books.id, (select count(*) from loans where loans.book_id = books.id) as loans__copies '.$grouped.' order by books.id desc limit 10 offset 0',
            'select count(*) as aggregate from (select books.id '.$grouped.') as aggregate_table',
        ], self::loggedQueries());
    }

    #[Test]
    public function it_counts_nothing_more_when_no_extension_applies(): void
    {
        config(['rest.extensions' => [NotingExtension::class]]);
        DB::enableQueryLog();

        $this->postJson('/api/books/search', ['search' => ['selects' => [['field' => 'id']], 'limit' => 10]])->assertOk();

        $this->assertSame(['select books.id from books order by books.id desc limit 10 offset 0', 'select count(*) as aggregate from books'], self::loggedQueries());
    }

    /** @return list<string> */
    private static function loggedQueries(): array
    {
        return array_map(static fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog());
    }
}
