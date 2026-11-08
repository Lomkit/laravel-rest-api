<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BookLabelController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToGroupTypesTest extends MorphTargetsTestCase
{
    use AssertsExactErrors;
    use ParsesSearchNodes;

    /**
     * @param  array<string, mixed>  $group
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('typedGroups')]
    public function it_filters_the_rows_with_a_list_for_each_type(array $group, array $ids): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => [['relation' => 'labelable', ...$group]], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
    }

    /** @return array<string, array{array<string, mixed>, list<int>}> */
    public static function typedGroups(): array
    {
        $dune = [['field' => 'title', 'value' => 'Dune']];
        $classics = [['field' => 'name', 'value' => 'Classics']];

        return [
            'one type' => [['types' => ['book' => $dune]], [1]],
            'a type that the relation does not declare next to a declared one' => [['types' => ['comment' => [['field' => 'text', 'value' => 'x']], 'book' => $dune]], [1]],
            'two types' => [['types' => ['book' => $dune, 'shelf' => $classics]], [1, 2]],
            'a type with an empty list' => [['types' => ['book' => []]], [1, 3]],
            'any declared type' => [[], [1, 2, 3]],
            'no matching target' => [['exists' => false, 'types' => ['book' => $dune, 'shelf' => $classics]], [3, 4, 5]],
        ];
    }

    #[Test]
    public function it_compiles_the_lists_of_a_group_into_one_condition_per_type(): void
    {
        $context = new RestContext(RestRequest::create('/api/labels/search', 'POST'), $this->app);
        $context->authorizer()->plan([Check::onClass($context->resource(BookResource::class), 'viewAny'), Check::onClass($context->resource(ShelfResource::class), 'viewAny')]);
        $query = Label::query();

        FilterGroupClause::of($this->parse($context->resource(LabelResource::class), ['filters' => [['relation' => 'labelable', 'exists' => false, 'types' => [
            'book' => [['field' => 'title', 'value' => 'Dune']],
            'shelf' => [],
        ]]]])->filters)->apply($query);

        $this->assertSame(
            'select * from labels where ((not (((labels.labelable_type = ? and exists (select * from books where labels.labelable_id = books.id and (books.title = ?))) or (labels.labelable_type = ? and exists (select * from shelves where labels.labelable_id = shelves.id)))) or labels.labelable_type is null))',
            str_replace(['"', '`'], '', $query->toSql()),
        );
        $this->assertSame(['book', 'Dune', 'shelf'], $query->getBindings());
    }

    /** @param array<string, mixed> $group */
    #[Test]
    #[DataProvider('closedGroups')]
    public function it_checks_the_types_that_a_group_reaches(array $group, int $status): void
    {
        Gate::policy(Shelf::class, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => [['relation' => 'labelable', ...$group]]]]);

        $response->assertStatus($status);
        $this->assertSame($status === 403 ? 0 : 1, count(DB::getQueryLog()));
    }

    /** @return array<string, array{array<string, mixed>, int}> */
    public static function closedGroups(): array
    {
        return [
            'every declared type' => [[], 403],
            'a listed type that is closed' => [['types' => ['shelf' => []]], 403],
            'a listed type that is open' => [['types' => ['book' => []]], 200],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedFilters')]
    public function it_refuses_a_filter_that_does_not_name_its_type(string $uri, array $filters, array $errors): void
    {
        Rest::resource('book-labels', BookLabelController::class)->register();

        $this->assertExactErrors($this->postJson($uri, ['search' => ['filters' => $filters]]), $errors);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, array<string, list<string>>}> */
    public static function refusedFilters(): array
    {
        $dune = [['field' => 'title', 'value' => 'Dune']];

        return [
            'no type' => ['/api/labels/search', [['relation' => 'labelable', 'types' => []]], ['search.filters.0.types' => ['The search.filters.0.types field must have at least 1 items.']]],
            'nested with several types' => ['/api/labels/search', [['relation' => 'labelable', 'nested' => $dune]], ['search.filters.0.nested' => ['The search.filters.0.nested field is prohibited: this relation has several types. Set it for each type, under types.']]],
            'only a type that the relation does not declare' => ['/api/labels/search', [['relation' => 'labelable', 'types' => ['comment' => []]]], ['search.filters.0.types' => ['The search.filters.0.types field must have at least 1 items.']]],
            'a field of another type' => ['/api/labels/search', [['relation' => 'labelable', 'types' => ['shelf' => $dune]]], ['search.filters.0.types.shelf.0.field' => ['The selected search.filters.0.types.shelf.0.field is invalid.']]],
            'a dotted field through several types' => ['/api/labels/search', [['field' => 'labelable.title', 'value' => 'Dune']], ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']]],
            'types next to nested with one target' => ['/book-labels/search', [['relation' => 'labelable', 'nested' => $dune, 'types' => ['book' => $dune]]], ['search.filters.0.types' => ['The search.filters.0.types field prohibits search.filters.0.nested from being present.']]],
            'types on a relation that is not a morph to' => ['/api/books/search', [['relation' => 'loan', 'types' => ['book' => []]]], ['search.filters.0.types' => ['The search.filters.0.types field is prohibited.']]],
        ];
    }
}
