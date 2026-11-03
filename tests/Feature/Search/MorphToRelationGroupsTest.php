<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Contracts\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ListedLabelController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToRelationGroupsTest extends PackageTestCase
{
    use ParsesSearchNodes;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Book::create(['id' => 1, 'title' => 'Dune']);
        Book::create(['id' => 2, 'title' => 'Emma']);
        Loan::create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Loan::create(['id' => 2, 'book_id' => 2, 'borrower' => 'Bob']);

        Label::create(['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);
        Label::create(['id' => 2, 'text' => 'novel', 'labelable_type' => Book::class, 'labelable_id' => 2]);
        Label::create(['id' => 3, 'text' => 'due', 'labelable_type' => Loan::class, 'labelable_id' => 1]);
        Label::create(['id' => 4, 'text' => 'ghost', 'labelable_type' => 'App\Nope', 'labelable_id' => 1]);
        Label::create(['id' => 5, 'text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]);
        Label::create(['id' => 6, 'text' => 'stray', 'labelable_type' => null, 'labelable_id' => 1]);
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('targetGroups')]
    public function it_filters_through_the_target_of_a_morph_to_relation(array $filters, array $ids): void
    {
        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $ids);
    }

    /** @return array<string, array{list<array<string, mixed>>, list<int>}> */
    public static function targetGroups(): array
    {
        $dune = [['field' => 'title', 'value' => 'Dune']];
        $due = ['field' => 'text', 'value' => 'due'];
        $emma = [['field' => 'title', 'value' => 'Emma']];

        return [
            'a matching target' => [[['relation' => 'labelable', 'nested' => $dune]], [1]],
            'any target' => [[['relation' => 'labelable']], [1, 2]],
            'no matching target' => [[['relation' => 'labelable', 'exists' => false, 'nested' => $dune]], [2, 3, 4, 5, 6]],
            'no target' => [[['relation' => 'labelable', 'exists' => false]], [3, 4, 5, 6]],
            'a target after an or' => [[$due, ['type' => 'or', 'relation' => 'labelable', 'nested' => $emma]], [2, 3]],
            'no target after an or' => [[$due, ['type' => 'or', 'relation' => 'labelable', 'exists' => false, 'nested' => $emma]], [1, 3, 4, 5, 6]],
        ];
    }

    #[Test]
    public function it_runs_no_query_on_the_stored_types(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => [['relation' => 'labelable', 'exists' => false]]]]);

        $response->assertOk();
        $this->assertSame([], array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $query): bool => str_contains($query, 'distinct') || str_contains($query, 'loans'),
        )));
    }

    #[Test]
    public function it_applies_the_search_query_of_the_target(): void
    {
        Rest::resource('listed-labels', ListedLabelController::class)->register();

        $present = $this->postJson('/listed-labels/search', ['search' => ['filters' => [['relation' => 'labelable']], 'sorts' => [['field' => 'id']]]]);
        $absent = $this->postJson('/listed-labels/search', ['search' => ['filters' => [['relation' => 'labelable', 'exists' => false]], 'sorts' => [['field' => 'id']]]]);

        $present->assertOk()->assertJsonPath('data.*.id', [2]);
        $absent->assertOk()->assertJsonPath('data.*.id', [1, 3, 4, 5, 6]);
    }

    #[Test]
    public function it_refuses_a_relation_group_on_a_target_the_user_cannot_list(): void
    {
        Gate::policy(Book::class, UnlistedPolicy::class);
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/search', ['search' => ['filters' => [['relation' => 'labelable', 'exists' => false]]]]);

        $response->assertForbidden()->assertJsonPath('message', 'This list is closed.');
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_compiles_the_absence_of_a_target_with_where_not(): void
    {
        $context = new RestContext(RestRequest::create('/api/labels/search', 'POST'), $this->app);
        $context->authorizer()->plan([Check::onClass($context->resource(BookResource::class), 'viewAny')]);
        $resource = $context->resource(LabelResource::class);
        $query = Label::query();

        FilterGroupClause::of($this->parse($resource, ['filters' => [['relation' => 'labelable', 'exists' => false, 'nested' => [['field' => 'title', 'value' => 'Dune']]]]])->filters)->apply($query);

        $this->assertSame(
            'select * from labels where ((not (((labels.labelable_type = ? and exists (select * from books where labels.labelable_id = books.id and (books.title = ?))))) or labels.labelable_type is null))',
            $this->sql($query),
        );
        $this->assertSame([Book::class, 'Dune'], $query->getBindings());
    }

    private function sql(QueryBuilder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}
