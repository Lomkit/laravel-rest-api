<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Search\Stubs\TalliedBookResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\TalliedLoanController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SortPathsTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('tallied-loans', TalliedLoanController::class)->register();
        TalliedBookResource::$reads = 0;
        Book::query()->create(['id' => 1, 'title' => 'Emma']);
        Book::query()->create(['id' => 2, 'title' => 'Dune']);
        Loan::query()->create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Loan::query()->create(['id' => 2, 'book_id' => 2, 'borrower' => 'Bob']);
    }

    /** @param list<int> $ids */
    #[Test]
    #[DataProvider('sorts')]
    public function it_reads_the_fields_of_a_related_resource_only_for_a_sort_on_its_relation(string $field, array $ids, int $reads): void
    {
        $response = $this->postJson('/tallied-loans/search', ['search' => ['selects' => [['field' => 'id']], 'sorts' => [['field' => $field, 'direction' => 'desc']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
        $this->assertSame($reads, TalliedBookResource::$reads);
    }

    /** @return array<string, array{string, list<int>, int}> */
    public static function sorts(): array
    {
        return [
            'a field of the resource' => ['borrower', [2, 1], 0],
            'a field of a related resource' => ['book.title', [1, 2], 1],
        ];
    }

    #[Test]
    #[DataProvider('unknownPaths')]
    public function it_refuses_a_path_that_names_no_sortable_field_of_a_to_one_relation(string $field): void
    {
        $response = $this->postJson('/tallied-loans/search', ['search' => ['sorts' => [['field' => $field]]]]);

        $this->assertExactErrors($response, ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']]);
    }

    /** @return array<string, array{string}> */
    public static function unknownPaths(): array
    {
        return [
            'an unknown field' => ['book.isbn'],
            'an unknown relation' => ['author.title'],
            'a path deeper than one relation' => ['book.loan.borrower'],
        ];
    }
}
