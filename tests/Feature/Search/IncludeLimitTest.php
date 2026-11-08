<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IncludeLimitTest extends PackageTestCase
{
    private const array ID = [['field' => 'id']];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1, 2] as $book) {
            Book::query()->create(['id' => $book, 'title' => 'Book '.$book]);
        }

        foreach (range(1, 24) as $id) {
            $book = $id <= 12 ? 1 : 2;
            Label::query()->create(['id' => $id, 'labelable_id' => $book, 'labelable_type' => Book::class, 'text' => 'Label '.$id]);
            Loan::query()->create(['id' => $id, 'book_id' => $book, 'borrower' => 'Reader '.$id]);
        }

        foreach (range(1, 12) as $shelf) {
            Shelf::query()->create(['id' => $shelf, 'name' => 'Shelf '.$shelf]);
            DB::table('book_shelf')->insert([['book_id' => 1, 'shelf_id' => $shelf], ['book_id' => 2, 'shelf_id' => $shelf]]);
        }
    }

    /**
     * @param  array<string, mixed>  $include
     * @param  list<list<int>>  $ids
     */
    #[Test]
    #[DataProvider('includes')]
    public function it_loads_the_default_number_of_rows_for_each_parent(array $include, string $key, array $ids): void
    {
        $response = $this->searchBooks([$include]);

        $response->assertOk();
        $this->assertSame($ids, array_map(fn (array $book): array => array_column($book[$key], 'id'), $response->json('data')));
    }

    /** @return array<string, array{array<string, mixed>, string, list<list<int>>}> */
    public static function includes(): array
    {
        return [
            'a morph many' => [['relation' => 'labels', 'selects' => self::ID], 'labels', [range(24, 15), range(12, 3)]],
            'a belongs to many' => [['relation' => 'shelves', 'selects' => self::ID], 'shelves', [range(12, 3), range(12, 3)]],
            'an aliased include' => [['relation' => 'labels', 'alias' => 'notes', 'selects' => self::ID], 'notes', [range(24, 15), range(12, 3)]],
            'an explicit limit' => [['relation' => 'labels', 'limit' => 25, 'selects' => self::ID], 'labels', [range(24, 13), range(12, 1)]],
            'the middle level of a dotted include' => [['relation' => 'labels.labelable', 'types' => [Book::class => ['selects' => self::ID]]], 'labels', [range(24, 15), range(12, 3)]],
        ];
    }

    #[Test]
    public function it_loads_one_row_of_a_has_one_of_many_within_the_default(): void
    {
        DB::enableQueryLog();

        $response = $this->searchBooks([['relation' => 'latestLoan', 'selects' => self::ID], ['relation' => 'loan', 'selects' => self::ID]]);

        $response->assertOk()
            ->assertJsonPath('data.*.latest_loan.id', [24, 12])
            ->assertJsonPath('data.*.loan.id', [24, 12]);
        $this->assertStringContainsString('row_number() over (partition by', DB::getQueryLog()[2]['query']);
    }

    #[Test]
    public function it_leaves_out_the_more_key_on_rows_with_only_to_one_includes(): void
    {
        $response = $this->searchBooks([['relation' => 'latestLoan', 'selects' => self::ID], ['relation' => 'loan', 'selects' => self::ID]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 2, 'latest_loan' => ['id' => 24], 'loan' => ['id' => 24]],
            ['id' => 1, 'latest_loan' => ['id' => 12], 'loan' => ['id' => 12]],
        ]);
    }

    #[Test]
    public function it_lists_a_limited_include_with_more_rows_on_the_rows_of_a_morph_to_target(): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => [
            'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [12, 24]]],
            'selects' => self::ID,
            'includes' => [['relation' => 'labelable', 'types' => [Book::class => ['selects' => self::ID, 'includes' => [['relation' => 'shelves', 'selects' => self::ID]]]]]],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.*.labelable.id', [2, 1])
            ->assertJsonPath('data.*.labelable.@more', [['shelves'], ['shelves']]);
        $this->assertSame([range(12, 3), range(12, 3)], array_map(fn (array $label): array => array_column($label['labelable']['shelves'], 'id'), $response->json('data')));
        $this->assertArrayNotHasKey('@more', $response->json('data.0'));
    }

    #[Test]
    public function it_limits_an_include_nested_under_an_owner(): void
    {
        $response = $this->postJson('/api/loans/search', ['search' => [
            'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [12, 24]]],
            'selects' => self::ID,
            'includes' => [['relation' => 'book', 'selects' => self::ID, 'includes' => [['relation' => 'labels', 'selects' => self::ID]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([range(24, 15), range(12, 3)], array_map(fn (array $loan): array => array_column($loan['book']['labels'], 'id'), $response->json('data')));
    }

    #[Test]
    public function it_loads_the_default_for_each_of_fifty_parents_in_one_query(): void
    {
        foreach (range(3, 50) as $book) {
            Book::query()->create(['id' => $book, 'title' => 'Book '.$book]);
            DB::table('labels')->insert(array_map(fn (int $label): array => ['id' => 24 + ($book - 3) * 11 + $label, 'labelable_id' => $book, 'labelable_type' => Book::class, 'text' => "Label {$book}.{$label}"], range(1, 11)));
        }

        DB::enableQueryLog();

        $response = $this->searchBooks([['relation' => 'labels', 'selects' => self::ID]]);

        $response->assertOk()->assertJsonCount(50, 'data');
        $this->assertSame(array_fill(0, 50, 10), array_map(fn (array $book): int => count($book['labels']), $response->json('data')));
        $this->assertSame(array_fill(0, 50, ['labels']), array_column($response->json('data'), '@more'));
        $this->assertCount(3, DB::getQueryLog());
    }

    /** @param list<array<string, mixed>> $includes */
    private function searchBooks(array $includes): TestResponse
    {
        return $this->postJson('/api/books/search', ['search' => ['selects' => self::ID, 'includes' => $includes]]);
    }
}
