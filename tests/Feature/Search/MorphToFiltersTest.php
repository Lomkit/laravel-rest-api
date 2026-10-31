<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ListedLabelController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToFiltersTest extends PackageTestCase
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

        $dune = Book::create(['title' => 'Dune']);
        $emma = Book::create(['title' => 'Emma']);
        $loan = Loan::create(['book_id' => $dune->id, 'borrower' => 'Ada']);
        Loan::create(['book_id' => $emma->id, 'borrower' => 'Bob']);

        Label::create(['text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => $dune->id]);
        Label::create(['text' => 'novel', 'labelable_type' => Book::class, 'labelable_id' => $emma->id]);
        Label::create(['text' => 'due', 'labelable_type' => Loan::class, 'labelable_id' => $loan->id]);
        Label::create(['text' => 'ghost', 'labelable_type' => 'App\Nope', 'labelable_id' => $dune->id]);
        Label::create(['text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]);
    }

    #[Test]
    public function it_filters_through_the_declared_target_of_a_morph_to_relation_only(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => [
            'filters' => [
                ['field' => 'text', 'value' => 'ghost'],
                ['field' => 'labelable.title', 'operator' => 'in', 'value' => ['Dune', 'Emma'], 'type' => 'or'],
            ],
            'sorts' => [['field' => 'text']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.text', ['classic', 'ghost', 'novel']);
        $this->assertSame([], array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $query): bool => str_contains($query, 'distinct') || str_contains($query, 'loans'),
        )));
    }

    #[Test]
    public function it_filters_through_a_morph_to_relation_in_the_middle_of_a_path(): void
    {
        $books = $this->postJson('/api/books/search', ['search' => ['filters' => [['field' => 'labels.labelable.title', 'value' => 'Emma']]]]);
        $labels = $this->postJson('/api/labels/search', ['search' => ['filters' => [['field' => 'labelable.loan.borrower', 'value' => 'Ada']]]]);

        $books->assertOk()->assertJsonPath('data.*.title', ['Emma']);
        $labels->assertOk()->assertJsonPath('data.*.text', ['classic']);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function targetFilters(): array
    {
        return [
            'the last relation of the path' => [['field' => 'labelable.title', 'operator' => 'in', 'value' => ['Dune', 'Emma']]],
            'a relation before another one' => [['field' => 'labelable.loan.borrower', 'operator' => 'in', 'value' => ['Ada', 'Bob']]],
        ];
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('targetFilters')]
    public function it_applies_the_perimeter_of_the_target_of_a_morph_to_relation(array $filter): void
    {
        Rest::resource('listed-labels', ListedLabelController::class)->register();

        $response = $this->postJson('/listed-labels/search', ['search' => ['filters' => [$filter]]]);

        $response->assertOk()->assertJsonPath('data.*.text', ['novel']);
    }

    /** @return array<string, array{class-string<Model>, string}> */
    public static function unlistedTargets(): array
    {
        return [
            'the target' => [Book::class, 'labelable.title'],
            'a relation of the target' => [Loan::class, 'labelable.loan.borrower'],
        ];
    }

    /** @param class-string<Model> $model */
    #[Test]
    #[DataProvider('unlistedTargets')]
    public function it_refuses_a_filter_through_a_morph_to_relation_the_user_cannot_list(string $model, string $field): void
    {
        Gate::policy($model, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => [['field' => $field, 'value' => 'Dune']]]]);

        $response->assertForbidden()->assertJsonPath('message', 'This list is closed.');
        $this->assertSame([], DB::getQueryLog());
    }
}
