<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OperationsTreeTest extends PackageTestCase
{
    private Book $emma;

    private Book $dune;

    private Shelf $classics;

    private Shelf $novels;

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
        $this->novels = Shelf::query()->create(['name' => 'Novels']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[Test]
    public function it_answers_each_entry_at_the_index_of_the_request(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->dune->id, 'attributes' => ['title' => 'Dune Messiah']],
            ['operation' => 'create', 'attributes' => ['title' => 'Ivanhoe'], 'relations' => [
                'shelves' => [
                    ['operation' => 'attach', 'key' => [$this->classics->id, $this->novels->id]],
                    ['operation' => 'create', 'attributes' => ['name' => 'Scott']],
                ],
                'labels' => [['operation' => 'create', 'attributes' => ['text' => 'Long']]],
                'loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Walter']],
            ]],
            ['operation' => 'create', 'attributes' => ['title' => 'Rob Roy']],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('operations', [
                ['operation' => 'update', 'key' => $this->dune->id],
                ['operation' => 'create', 'key' => $this->bookId('Ivanhoe'), 'relations' => [
                    'shelves' => [
                        ['operation' => 'attach', 'key' => [$this->classics->id, $this->novels->id]],
                        ['operation' => 'create', 'key' => Shelf::firstWhere('name', 'Scott')?->id],
                    ],
                    'labels' => [['operation' => 'create', 'key' => Label::query()->sole()->id]],
                    'loan' => ['operation' => 'create', 'key' => Loan::query()->sole()->id],
                ]],
                ['operation' => 'create', 'key' => $this->bookId('Rob Roy')],
            ]);
        $this->assertSame(['operations'], array_keys($response->json()));
    }

    #[Test]
    public function it_echoes_the_operations_of_a_many_to_many_relation_as_sent(): void
    {
        $this->emma->shelves()->attach([$this->classics->id, $this->novels->id]);
        $scienceFiction = Shelf::query()->create(['name' => 'Science fiction']);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->emma->id,
            'relations' => ['shelves' => [
                ['operation' => 'sync', 'key' => [$scienceFiction->id]],
                ['operation' => 'toggle', 'key' => [$this->classics->id, $scienceFiction->id]],
                ['operation' => 'detach', 'key' => [$this->classics->id]],
            ]],
        ]]]);

        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'update',
            'key' => $this->emma->id,
            'relations' => ['shelves' => [
                ['operation' => 'sync', 'key' => [$scienceFiction->id]],
                ['operation' => 'toggle', 'key' => [$this->classics->id, $scienceFiction->id]],
                ['operation' => 'detach', 'key' => [$this->classics->id]],
            ]],
        ]]]);
        $this->assertSame(0, $this->emma->shelves()->count());
    }

    #[Test]
    public function it_nests_a_belongs_to_entry_under_the_entry_that_owns_it(): void
    {
        $response = $this->postJson('/api/loans/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['borrower' => 'Grace'],
            'relations' => ['book' => ['operation' => 'create', 'attributes' => ['title' => 'Persuasion']]],
        ]]]);

        $loan = Loan::query()->sole();
        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'create',
            'key' => $loan->id,
            'relations' => ['book' => ['operation' => 'create', 'key' => $this->bookId('Persuasion')]],
        ]]]);
        $this->assertSame($this->bookId('Persuasion'), $loan->book_id);
    }

    /** @param array<string, class-string> $map */
    #[Test]
    #[DataProvider('morphMaps')]
    public function it_echoes_the_type_of_a_morph_to_entry(array $map, string $type): void
    {
        Relation::morphMap($map, false);
        $kept = Label::query()->create(['text' => 'Kept', 'labelable_id' => $this->dune->id, 'labelable_type' => $type]);

        $response = $this->postJson('/api/labels/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['text' => 'Classic'], 'relations' => ['labelable' => ['operation' => 'attach', 'key' => $this->emma->id]]],
            ['operation' => 'update', 'key' => $kept->id, 'relations' => ['labelable' => ['operation' => 'detach', 'key' => $this->dune->id]]],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [
            ['operation' => 'create', 'key' => Label::firstWhere('text', 'Classic')?->id, 'relations' => [
                'labelable' => ['operation' => 'attach', 'type' => $type, 'key' => $this->emma->id],
            ]],
            ['operation' => 'update', 'key' => $kept->id, 'relations' => [
                'labelable' => ['operation' => 'detach', 'type' => $type, 'key' => $this->dune->id],
            ]],
        ]]);
    }

    /** @return array<string, array{array<string, class-string>, string}> */
    public static function morphMaps(): array
    {
        return [
            'the class without a morph map' => [[], Book::class],
            'the alias of the morph map' => [['book' => Book::class], 'book'],
        ];
    }

    #[Test]
    public function it_answers_no_tree_when_a_write_is_refused(): void
    {
        Gate::before(fn (mixed $user, string $ability, array $arguments): ?bool => $ability === 'update' && ($arguments[0] ?? null) instanceof Book ? false : null);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Ivanhoe']],
            ['operation' => 'update', 'key' => $this->dune->id, 'attributes' => ['title' => 'Dune Messiah']],
        ]]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.')
            ->assertJsonMissingPath('operations');
        $this->assertSame([$this->emma->id => 'Emma', $this->dune->id => 'Dune'], Book::pluck('title', 'id')->all());
    }

    private function bookId(string $title): int
    {
        return Book::query()
            ->where('title', $title)
            ->sole()
            ->id;
    }
}
