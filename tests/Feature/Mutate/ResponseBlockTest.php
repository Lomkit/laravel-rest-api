<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\HeadedBookResponse;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResponseBlockTest extends PackageTestCase
{
    private const array GRANTED = ['allowed' => true, 'message' => null];

    private Book $emma;

    private Book $dune;

    private Shelf $classics;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->emma = Book::query()->create(['title' => 'Emma']);
        $this->dune = Book::query()->create(['title' => 'Dune']);
        $this->classics = Shelf::query()->create(['name' => 'Classics']);
        Label::query()->create(['text' => 'Classic', 'labelable_id' => $this->dune->id, 'labelable_type' => Book::class]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        BookResource::$response = Response::class;

        parent::tearDown();
    }

    #[Test]
    public function it_returns_the_record_of_each_root_entry_at_its_index(): void
    {
        $response = $this->postJson('/api/books/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => (string) $this->dune->id, 'attributes' => ['title' => 'Dune Messiah']],
                ['operation' => 'create', 'attributes' => ['title' => 'Ivanhoe'], 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [$this->classics->id]]]]],
                ['operation' => 'update', 'key' => $this->dune->id, 'attributes' => ['title' => 'Children of Dune']],
            ],
            'response' => [
                'selects' => [['field' => 'title']],
                'aggregates' => [['relation' => 'shelves', 'type' => 'count']],
                'includes' => [['relation' => 'labels', 'selects' => [['field' => 'text']]]],
                'gates' => ['update'],
            ],
        ]);

        $dune = ['title' => 'Children of Dune', 'shelves_count' => 0, 'gates' => ['authorized_to_update' => self::GRANTED], 'labels' => [['text' => 'Classic']], '@more' => []];
        $response
            ->assertOk()
            ->assertExactJson([
                'operations' => [
                    ['operation' => 'update', 'key' => $this->dune->id],
                    ['operation' => 'create', 'key' => $this->bookId('Ivanhoe'), 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [$this->classics->id]]]]],
                    ['operation' => 'update', 'key' => $this->dune->id],
                ],
                'records' => [
                    $dune,
                    ['title' => 'Ivanhoe', 'shelves_count' => 1, 'gates' => ['authorized_to_update' => self::GRANTED], 'labels' => [], '@more' => []],
                    $dune,
                ],
            ]);
    }

    #[Test]
    public function it_returns_an_aggregate_of_a_relation_path_in_each_record(): void
    {
        Loan::query()->create(['book_id' => $this->dune->id, 'borrower' => 'Zoe']);
        Loan::query()->create(['book_id' => $this->dune->id, 'borrower' => 'Adam']);

        $response = $this->postJson('/api/books/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $this->dune->id, 'attributes' => ['title' => 'Dune Messiah']],
                ['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Emma Woodhouse']],
            ],
            'response' => ['selects' => [['field' => 'title']], 'aggregates' => [['relation' => 'loan.book', 'type' => 'count']]],
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'operations' => [['operation' => 'update', 'key' => $this->dune->id], ['operation' => 'update', 'key' => $this->emma->id]],
                'records' => [['title' => 'Dune Messiah', 'loan_book_count' => 1], ['title' => 'Emma Woodhouse', 'loan_book_count' => 0]],
            ]);
    }

    #[Test]
    public function it_answers_the_operations_alone_without_a_response_block(): void
    {
        $this->postJson('/api/books/mutate', ['mutate' => [['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Persuasion']]]])
            ->assertOk()
            ->assertExactJson(['operations' => [['operation' => 'update', 'key' => $this->emma->id]]]);
    }

    #[Test]
    public function it_returns_the_selectable_fields_for_an_empty_response_block(): void
    {
        $this->postJson('/api/books/mutate', ['mutate' => [['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Persuasion']]], 'response' => []])
            ->assertOk()
            ->assertExactJson([
                'operations' => [['operation' => 'update', 'key' => $this->emma->id]],
                'records' => [['id' => $this->emma->id, 'title' => 'Persuasion']],
            ]);
    }

    #[Test]
    public function it_limits_an_included_relation_per_record(): void
    {
        foreach (range(1, 11) as $number) {
            Label::query()->create(['text' => 'Shelf mark '.$number, 'labelable_id' => $this->emma->id, 'labelable_type' => Book::class]);
        }

        $response = $this->postJson('/api/books/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Persuasion']],
                ['operation' => 'update', 'key' => $this->dune->id, 'attributes' => ['title' => 'Dune Messiah']],
            ],
            'response' => [
                'selects' => [['field' => 'title']],
                'includes' => [['relation' => 'labels', 'limit' => 10, 'selects' => [['field' => 'text']]]],
            ],
        ]);

        $marks = array_map(fn (int $number): array => ['text' => 'Shelf mark '.$number], range(11, 2));
        $response
            ->assertOk()
            ->assertJsonPath('records', [
                ['title' => 'Persuasion', 'labels' => $marks, '@more' => ['labels']],
                ['title' => 'Dune Messiah', 'labels' => [['text' => 'Classic']], '@more' => []],
            ]);
    }

    #[Test]
    public function it_renders_the_records_through_the_response_of_the_resource(): void
    {
        BookResource::$response = HeadedBookResponse::class;

        $this->postJson('/api/books/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Persuasion']]],
            'response' => ['selects' => [['field' => 'title']]],
        ])->assertJsonPath('records', [['title' => 'Persuasion', 'heading' => 'PERSUASION']]);
    }

    #[Test]
    public function it_reads_the_records_with_one_query_per_level(): void
    {
        DB::enableQueryLog();
        $this->postJson('/api/books/mutate', ['mutate' => $this->renameAndCreate($this->emma, 'Persuasion', 'Ivanhoe')])->assertOk();
        $written = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->postJson('/api/books/mutate', [
            'mutate' => $this->renameAndCreate($this->dune, 'Dune Messiah', 'Rob Roy'),
            'response' => ['aggregates' => [['relation' => 'shelves', 'type' => 'count']], 'includes' => [['relation' => 'labels']]],
        ])->assertOk();

        $queries = DB::getQueryLog();
        $this->assertCount($written + 2, $queries);
        $this->assertSame(['select', 'select'], array_map(fn (array $query): string => Str::before($query['query'], ' '), array_slice($queries, -2)));
    }

    private function bookId(string $title): int
    {
        return Book::query()
            ->where('title', $title)
            ->sole()
            ->id;
    }

    /** @return list<array<string, mixed>> */
    private function renameAndCreate(Book $book, string $title, string $created): array
    {
        return [
            ['operation' => 'update', 'key' => $book->id, 'attributes' => ['title' => $title]],
            ['operation' => 'create', 'attributes' => ['title' => $created]],
        ];
    }
}
