<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BookLabelController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToIncludeTypesTest extends MorphTargetsTestCase
{
    use AssertsExactErrors;

    private const array ID = [['field' => 'id']];

    #[Test]
    public function it_loads_every_declared_type_without_types(): void
    {
        DB::enableQueryLog();

        $response = $this->search([['relation' => 'labelable']]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [
            ['@type' => 'book', 'id' => 1, 'title' => 'Dune'],
            ['@type' => 'shelf', 'id' => 1, 'name' => 'Classics'],
            ['@type' => 'book', 'id' => 2, 'title' => 'Emma'],
            null,
            null,
        ]);
        $this->assertSame([
            'select labels.id, labels.labelable_type, labels.labelable_id from labels order by labels.id asc limit 50 offset 0',
            'select books.id, books.title from books where books.id in (1, 2)',
            'select shelves.id, shelves.name from shelves where shelves.id in (1)',
        ], self::loggedQueries());
    }

    #[Test]
    public function it_loads_only_the_listed_types_and_checks_no_other(): void
    {
        Gate::policy(Book::class, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->search([['relation' => 'labelable', 'types' => ['shelf' => []]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [null, ['@type' => 'shelf', 'id' => 1, 'name' => 'Classics'], null, null, null]);
        $this->assertCount(2, self::loggedQueries());
    }

    #[Test]
    public function it_ignores_a_type_that_the_relation_does_not_declare_and_an_unknown_key_of_a_block(): void
    {
        DB::enableQueryLog();

        $response = $this->search([['relation' => 'labelable', 'types' => ['comment' => [], Book::class => [], 'shelf' => ['selects' => [['field' => 'name']], 'select' => []]]]]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'labelable' => null],
            ['id' => 2, 'labelable' => ['@type' => 'shelf', 'name' => 'Classics']],
            ['id' => 3, 'labelable' => null],
            ['id' => 4, 'labelable' => null],
            ['id' => 5, 'labelable' => null],
        ]);
        $this->assertSame([
            'select labels.id, labels.labelable_type, labels.labelable_id from labels order by labels.id asc limit 50 offset 0',
            'select shelves.name, shelves.id from shelves where shelves.id in (1)',
        ], self::loggedQueries());
    }

    /** @param array<string, mixed> $include */
    #[Test]
    #[DataProvider('closedTypes')]
    public function it_refuses_an_include_that_reaches_a_type_the_user_cannot_list(array $include): void
    {
        Gate::policy(Shelf::class, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->search([$include]);

        $response->assertForbidden()->assertJsonPath('message', 'This list is closed.');
        $this->assertSame([], self::loggedQueries());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function closedTypes(): array
    {
        return [
            'every declared type' => [['relation' => 'labelable']],
            'a listed type' => [['relation' => 'labelable', 'types' => ['shelf' => []]]],
        ];
    }

    #[Test]
    public function it_searches_each_type_with_its_block(): void
    {
        $response = $this->search([['relation' => 'labelable', 'types' => [
            'book' => [
                'selects' => [['field' => 'title']],
                'filters' => [['field' => 'title', 'value' => 'Dune']],
                'aggregates' => [['relation' => 'labels', 'type' => 'count']],
                'includes' => [['relation' => 'loan', 'selects' => self::ID]],
            ],
            'shelf' => ['selects' => [['field' => 'name']], 'gates' => ['view']],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.labelable', [
            ['@type' => 'book', 'title' => 'Dune', 'labels_count' => 1, 'loan' => ['id' => 1]],
            ['@type' => 'shelf', 'name' => 'Classics', 'gates' => ['authorized_to_view' => ['allowed' => true, 'message' => null]]],
            null,
            null,
            null,
        ]);
    }

    #[Test]
    public function it_loads_a_morph_to_include_in_a_type_block_and_at_the_end_of_a_dotted_include(): void
    {
        $nested = $this->search([['relation' => 'labelable', 'types' => ['book' => [
            'selects' => self::ID,
            'includes' => [['relation' => 'labels', 'selects' => self::ID, 'includes' => [['relation' => 'labelable', 'types' => ['book' => ['selects' => [['field' => 'title']]]]]]]],
        ]]]]);
        $dotted = $this->postJson('/api/books/search', ['search' => ['selects' => self::ID, 'sorts' => [['field' => 'id']], 'includes' => [
            ['relation' => 'labels.labelable', 'types' => ['book' => ['selects' => [['field' => 'title']]]]],
        ]]]);

        $nested->assertOk()->assertJsonPath('data.0.labelable.labels', [['id' => 1, 'labelable' => ['@type' => 'book', 'title' => 'Dune']]]);
        $dotted->assertOk()->assertJsonPath('data.*.labels.*.labelable.title', ['Dune', 'Emma']);
    }

    #[Test]
    public function it_aliases_a_morph_to_include_with_types(): void
    {
        $response = $this->search([['relation' => 'labelable', 'alias' => 'target', 'types' => ['shelf' => ['selects' => [['field' => 'name']]]]]]);

        $response->assertOk()->assertJsonPath('data.*.target', [null, ['@type' => 'shelf', 'name' => 'Classics'], null, null, null]);
    }

    /**
     * @param  array<string, mixed>  $include
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedIncludes')]
    public function it_refuses_an_include_that_does_not_set_its_search_per_type(array $include, array $errors): void
    {
        DB::enableQueryLog();

        $this->assertExactErrors($this->search([$include]), $errors);
        $this->assertSame([], self::loggedQueries());
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function refusedIncludes(): array
    {
        $several = 'field is prohibited: this relation has several types. Set it for each type, under types.';
        $owner = 'field is prohibited: this relation loads at most one record per parent.';

        return [
            'selects next to types' => [['relation' => 'labelable', 'selects' => self::ID, 'types' => ['book' => []]], ['search.includes.0.selects' => ['The search.includes.0.selects '.$several]]],
            'filters without types' => [['relation' => 'labelable', 'filters' => [['field' => 'id', 'value' => 1]]], ['search.includes.0.filters' => ['The search.includes.0.filters '.$several]]],
            'only types that the relation does not declare' => [['relation' => 'labelable', 'types' => ['comment' => [], Book::class => []]], ['search.includes.0.types' => ['The search.includes.0.types field must have at least 1 items.']]],
            'a known include key in a block' => [['relation' => 'labelable', 'types' => ['book' => ['relation' => 'loan', 'page' => 2, 'select' => []]]], ['search.includes.0.types.book.relation' => ['The search.includes.0.types.book.relation field is prohibited.'], 'search.includes.0.types.book.page' => ['The search.includes.0.types.book.page field is prohibited.']]],
            'no type' => [['relation' => 'labelable', 'types' => []], ['search.includes.0.types' => ['The search.includes.0.types field must have at least 1 items.']]],
            'a limit and sorts in a block' => [['relation' => 'labelable', 'types' => ['book' => ['limit' => 10, 'sorts' => [['field' => 'id']]]]], ['search.includes.0.types.book.limit' => ['The search.includes.0.types.book.limit '.$owner], 'search.includes.0.types.book.sorts' => ['The search.includes.0.types.book.sorts '.$owner]]],
            'a field of another type' => [['relation' => 'labelable', 'types' => ['book' => ['selects' => [['field' => 'name']]]]], ['search.includes.0.types.book.selects.0.field' => ['The selected search.includes.0.types.book.selects.0.field is invalid.']]],
            'a dotted include through the relation' => [['relation' => 'labelable.loan'], ['search.includes.0.relation' => ['The search.includes.0.relation field is not a valid or allowed relation for this resource.']]],
        ];
    }

    #[Test]
    public function it_takes_the_one_type_of_a_single_target_flat_or_under_types(): void
    {
        Rest::resource('book-labels', BookLabelController::class)->register();

        $flat = $this->postJson('/book-labels/search', ['search' => ['selects' => self::ID, 'sorts' => [['field' => 'id']], 'includes' => [['relation' => 'labelable', 'selects' => [['field' => 'title']]]]]]);
        $typed = $this->postJson('/book-labels/search', ['search' => ['selects' => self::ID, 'sorts' => [['field' => 'id']], 'includes' => [['relation' => 'labelable', 'types' => ['book' => ['selects' => [['field' => 'title']]]]]]]]);
        $both = $this->postJson('/book-labels/search', ['search' => ['includes' => [['relation' => 'labelable', 'selects' => [['field' => 'title']], 'types' => ['book' => []]]]]]);

        $titles = [['@type' => 'book', 'title' => 'Dune'], null, ['@type' => 'book', 'title' => 'Emma'], null, null];
        $flat->assertOk()->assertJsonPath('data.*.labelable', $titles);
        $typed->assertOk()->assertJsonPath('data.*.labelable', $titles);
        $this->assertExactErrors($both, ['search.includes.0.types' => ['The search.includes.0.types field prohibits search.includes.0.selects from being present.']]);
    }

    /** @param list<array<string, mixed>> $includes */
    private function search(array $includes): TestResponse
    {
        return $this->postJson('/api/labels/search', ['search' => ['selects' => self::ID, 'sorts' => [['field' => 'id']], 'includes' => $includes]]);
    }
}
