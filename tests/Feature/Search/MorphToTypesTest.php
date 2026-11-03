<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\CastLabelController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToTypesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([], false);
        Book::create(['id' => 1, 'title' => 'Dune']);
        Book::create(['id' => 2, 'title' => 'Emma']);
        Loan::create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Label::create(['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    /** @return array<string, array{string|null, int|null}> */
    public static function strayTargets(): array
    {
        return [
            'another model' => [Loan::class, 1],
            'an unknown type' => ['App\Nope', 1],
            'a type that differs only by case' => [strtolower(Book::class), 2],
            'a type without a key' => [Book::class, null],
            'a key without a type' => [null, 2],
            'a missing record' => [Book::class, 9],
        ];
    }

    #[Test]
    #[DataProvider('strayTargets')]
    public function it_renders_null_for_a_target_that_no_declared_type_holds(?string $type, ?int $key): void
    {
        Label::create(['id' => 2, 'text' => 'stray', 'labelable_type' => $type, 'labelable_id' => $key]);

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()
            ->assertJsonPath('data.0.labelable.id', 1)
            ->assertJsonPath('data.1.labelable', null);
    }

    #[Test]
    public function it_loads_each_declared_type_with_one_query(): void
    {
        Label::create(['id' => 2, 'text' => 'due', 'labelable_type' => Loan::class, 'labelable_id' => 1]);
        Label::create(['id' => 3, 'text' => 'ghost', 'labelable_type' => 'App\Nope', 'labelable_id' => 1]);
        Label::create(['id' => 4, 'text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]);
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable.id', [1, null, null, null]);
        $this->assertCount(3, DB::getQueryLog());
        $this->assertSame([], array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $query): bool => str_contains($query, 'loans'))));
    }

    #[Test]
    public function it_matches_the_type_of_a_morph_map(): void
    {
        Relation::morphMap(['book' => Book::class], false);
        Label::create(['id' => 2, 'text' => 'novel', 'labelable_type' => 'book', 'labelable_id' => 2]);

        $response = $this->postJson('/api/labels/search', ['search' => ['includes' => [['relation' => 'labelable']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [null, ['@type' => 'book', 'id' => 2, 'title' => 'Emma']]);
    }

    #[Test]
    public function it_filters_a_target_of_a_morph_map(): void
    {
        Relation::morphMap(['book' => Book::class], false);
        Label::create(['id' => 2, 'text' => 'novel', 'labelable_type' => 'book', 'labelable_id' => 2]);
        Label::create(['id' => 3, 'text' => 'saga', 'labelable_type' => 'book', 'labelable_id' => 1]);

        $response = $this->postJson('/api/labels/search', ['search' => [
            'includes' => [['relation' => 'labelable', 'filters' => [['field' => 'title', 'value' => 'Dune']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [null, null, ['@type' => 'book', 'id' => 1, 'title' => 'Dune']]);
    }

    #[Test]
    public function it_matches_the_stored_type_of_a_cast_type_column(): void
    {
        Rest::resource('cast-labels', CastLabelController::class)->register();
        Relation::morphMap(['book' => Book::class], false);
        Label::query()
            ->whereKey(1)
            ->update(['labelable_type' => 'book']);

        $response = $this->postJson('/cast-labels/search', ['search' => ['includes' => [['relation' => 'labelable']]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [['@type' => 'book', 'id' => 1, 'title' => 'Dune']]);
    }
}
