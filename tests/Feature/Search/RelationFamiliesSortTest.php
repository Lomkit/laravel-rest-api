<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ThreadedCommentController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationFamiliesSortTest extends PackageTestCase
{
    use SeedsNewsroomRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Newsroom];
    }

    /**
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     */
    #[Test]
    #[DataProvider('bookRelations')]
    public function it_sorts_on_a_field_of_a_to_one_relation(string $field, array $nullsFirst, array $nullsLast): void
    {
        foreach ([[1, 'Dune'], [2, 'Emma'], [3, 'Ulysses']] as [$id, $title]) {
            Book::create(['id' => $id, 'title' => $title]);
        }

        Loan::create(['id' => 1, 'book_id' => 1, 'borrower' => 'Zoe']);
        Loan::create(['id' => 2, 'book_id' => 1, 'borrower' => 'Adam']);
        Loan::create(['id' => 3, 'book_id' => 2, 'borrower' => 'Mia']);
        Label::create(['id' => 1, 'labelable_type' => Book::class, 'labelable_id' => 2, 'text' => 'Classic']);
        Label::create(['id' => 2, 'labelable_type' => Book::class, 'labelable_id' => 3, 'text' => 'Archive']);

        $response = $this->postJson('/api/books/search', ['search' => ['sorts' => [['field' => $field], ['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver($nullsFirst, $nullsLast));
    }

    /** @return array<string, array{string, list<int>, list<int>}> */
    public static function bookRelations(): array
    {
        return [
            'a has one relation, through the greatest value of its rows' => ['loan.borrower', [3, 2, 1], [2, 1, 3]],
            'a has one of many relation' => ['latestLoan.borrower', [3, 1, 2], [1, 2, 3]],
            'a morph one relation' => ['label.text', [1, 3, 2], [3, 2, 1]],
        ];
    }

    #[Test]
    public function it_sorts_on_a_field_of_a_has_one_through_relation(): void
    {
        $this->seedNewsroomRows();

        $response = $this->postJson('/news-desks/search', ['search' => ['sorts' => [['field' => 'leadSummary.body'], ['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([3, 2, 1], [2, 1, 3]));
    }

    #[Test]
    public function it_sorts_on_a_relation_to_the_same_table(): void
    {
        $this->seedNewsroomRows();
        Rest::resource('threaded-comments', ThreadedCommentController::class)->register();

        $response = $this->postJson('/threaded-comments/search', ['search' => ['sorts' => [['field' => 'parent.body', 'direction' => 'desc'], ['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([3, 4, 1, 2], [1, 2, 3, 4]));
    }

    /**
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     * @return list<int>
     */
    private function byDriver(array $nullsFirst, array $nullsLast): array
    {
        return DB::connection()->getDriverName() === 'pgsql' ? $nullsLast : $nullsFirst;
    }
}
