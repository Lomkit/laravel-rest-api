<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BookLabelController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OwnerIncludeTest extends PackageTestCase
{
    private const string REFUSED = 'field is prohibited: this relation loads at most one record per parent.';

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
            Loan::query()->create(['id' => $id, 'book_id' => $id, 'borrower' => 'Reader '.$id]);
            Label::query()->create(['id' => $id, 'labelable_id' => $id, 'labelable_type' => Book::class, 'text' => 'Label '.$id]);
        }
    }

    #[Test]
    #[DataProvider('owners')]
    public function it_loads_the_owner_of_every_row_without_a_limit_or_an_order(string $uri, string $relation): void
    {
        DB::enableQueryLog();

        $response = $this->postJson($uri, ['search' => ['selects' => [['field' => 'id']], 'includes' => [['relation' => $relation]]]]);

        $response->assertOk()->assertJsonPath("data.*.{$relation}.id", range(12, 1));
        $query = DB::getQueryLog()[1]['query'];
        $this->assertStringContainsString('books', $query);
        $this->assertStringNotContainsString('order by', $query);
        $this->assertStringNotContainsString('limit', $query);
    }

    /** @return array<string, array{string, string}> */
    public static function owners(): array
    {
        return [
            'a belongs to' => ['/api/loans/search', 'book'],
            'a morph to' => ['/api/labels/search', 'labelable'],
        ];
    }

    #[Test]
    #[DataProvider('ownerMiddleLevels')]
    public function it_loads_the_owner_a_dotted_include_goes_through_without_a_limit_or_an_order(string $uri, string $relation, string $owner): void
    {
        Rest::resource('book-labels', BookLabelController::class)->register();
        DB::enableQueryLog();

        $response = $this->postJson($uri, ['search' => ['selects' => [['field' => 'id']], 'includes' => [['relation' => $relation]]]]);

        $response->assertOk()->assertJsonPath("data.*.{$owner}.id", range(12, 1));
        $query = DB::getQueryLog()[1]['query'];
        $this->assertStringContainsString('books', $query);
        $this->assertStringNotContainsString('order by', $query);
        $this->assertStringNotContainsString('limit', $query);
    }

    /** @return array<string, array{string, string, string}> */
    public static function ownerMiddleLevels(): array
    {
        return [
            'a belongs to' => ['/api/loans/search', 'book.labels', 'book'],
            'a morph to' => ['/book-labels/search', 'labelable.loan', 'labelable'],
        ];
    }

    #[Test]
    public function it_accepts_a_limit_on_a_dotted_include_that_goes_through_an_owner(): void
    {
        $response = $this->postJson('/api/loans/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'book.labels', 'limit' => 10, 'selects' => [['field' => 'id']]]],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.book.labels.*.id', range(12, 1));
    }

    #[Test]
    public function it_gives_only_the_depth_error_for_an_owner_include_that_goes_too_deep(): void
    {
        $response = $this->postJson('/api/books/search', ['search' => ['includes' => [
            ['relation' => 'loan.book.loan.book', 'limit' => 10, 'sorts' => [['field' => 'id']]],
        ]]]);

        $message = 'The search.includes.0.relation field goes deeper than the limit of 3 include levels.';
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.includes.0.relation' => [$message]]]);
    }

    /**
     * @param  array<string, mixed>  $include
     * @param  list<string>  $keys
     */
    #[Test]
    #[DataProvider('refusedBounds')]
    public function it_refuses_a_limit_or_sorts_on_an_owner_include(string $uri, array $include, array $keys): void
    {
        $response = $this->postJson($uri, ['search' => ['includes' => [$include]]]);

        $messages = array_map(fn (string $key): string => "The {$key} ".self::REFUSED, $keys);
        $response->assertUnprocessable()->assertExactJson([
            'message' => $messages[0].(count($keys) > 1 ? ' (and 1 more error)' : ''),
            'errors' => array_combine($keys, array_map(fn (string $message): array => [$message], $messages)),
        ]);
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>}> */
    public static function refusedBounds(): array
    {
        return [
            'a limit on a belongs to' => ['/api/loans/search', ['relation' => 'book', 'limit' => 10], ['search.includes.0.limit']],
            'sorts on a belongs to' => ['/api/loans/search', ['relation' => 'book', 'sorts' => [['field' => 'title']]], ['search.includes.0.sorts']],
            'a limit and sorts on a morph to' => ['/api/labels/search', ['relation' => 'labelable', 'limit' => 10, 'sorts' => [['field' => 'title']]], ['search.includes.0.limit', 'search.includes.0.sorts']],
            'values that are not valid' => ['/api/loans/search', ['relation' => 'book', 'limit' => 7, 'sorts' => [['field' => 'isbn']]], ['search.includes.0.limit', 'search.includes.0.sorts']],
            'a limit at the end of a dotted path' => ['/api/books/search', ['relation' => 'labels.labelable', 'limit' => 10], ['search.includes.0.limit']],
            'sorts on a nested owner include' => ['/api/books/search', ['relation' => 'labels', 'includes' => [['relation' => 'labelable', 'sorts' => [['field' => 'id']]]]], ['search.includes.0.includes.0.sorts']],
        ];
    }
}
