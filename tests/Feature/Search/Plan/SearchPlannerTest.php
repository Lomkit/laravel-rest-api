<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use Closure;
use LogicException;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\InstructionClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Query\Clauses\ScopeClause;
use Lomkit\Rest\Query\Clauses\SortClause;
use Lomkit\Rest\Query\Clauses\TrashedClause;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\Plan\DefaultOrderClause;
use Lomkit\Rest\Search\Plan\IncludeClause;
use Lomkit\Rest\Search\Plan\KeyOrderClause;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Search\Plan\SqlRows;
use Lomkit\Rest\Search\RelevanceSort;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;
use Lomkit\Rest\Text\Engine\EngineSort;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchPlannerTest extends PackageTestCase
{
    use ParsesSearchNodes;

    private ?RestContext $context = null;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  array<string, mixed>  $search
     * @param  list<class-string<Clause>>  $clauses
     */
    #[Test]
    #[DataProvider('levels')]
    public function it_orders_the_clauses_of_a_level(string $resource, array $search, array $clauses): void
    {
        $plan = $this->paged($this->listed($resource), $search);

        $this->assertSame($clauses, array_map(get_class(...), $plan->clauses));
    }

    /** @return iterable<string, array{class-string<Resource>, array<string, mixed>, list<class-string<Clause>>}> */
    public static function levels(): iterable
    {
        yield 'no sorts' => [PostResource::class, [], [PerimeterClause::class, DefaultOrderClause::class, KeyOrderClause::class]];
        yield 'filters and sorts' => [
            PostResource::class,
            ['filters' => [['field' => 'views', 'value' => 1]], 'sorts' => [['field' => 'title'], ['field' => 'views']]],
            [PerimeterClause::class, FilterGroupClause::class, SortClause::class, SortClause::class, KeyOrderClause::class],
        ];
        yield 'trashed' => [AuthorResource::class, ['trashed' => 'with'], [PerimeterClause::class, TrashedClause::class, DefaultOrderClause::class, KeyOrderClause::class]];
        yield 'scopes and instructions' => [
            PostResource::class,
            ['scopes' => [['name' => 'popular']], 'instructions' => [['name' => 'in-status', 'fields' => [['name' => 'status', 'value' => 'draft']]]]],
            [PerimeterClause::class, DefaultOrderClause::class, ScopeClause::class, InstructionClause::class, KeyOrderClause::class],
        ];
    }

    #[Test]
    public function it_gives_each_level_its_limit(): void
    {
        $posts = $this->listed(PostResource::class);
        $authors = $this->listed(AuthorResource::class);
        $default = $this->paged($posts, [])->limit;
        $sent = $this->paged($posts, ['limit' => 25, 'page' => 3])->limit;
        $include = $this->paged($authors, ['includes' => [['relation' => 'posts', 'limit' => 10]]])->includes[0];
        $aggregate = Author::query();
        $this->paged($authors, ['aggregates' => [['relation' => 'posts', 'type' => 'count']]])->aggregates[0]->apply($aggregate);

        $this->assertSame([50, 1], [$default->perPage(), $default->page()]);
        $this->assertSame([25, 3], [$sent->perPage(), $sent->page()]);
        $this->assertSame('select * from posts limit 10', $this->limited($this->all($posts, ['limit' => 10])->limit));
        $this->assertSame('select * from posts', $this->limited($this->all($posts, [])->limit));
        $this->assertInstanceOf(IncludeClause::class, $include);
        $this->assertSame('select * from posts limit 10', $this->limited($include->plan->limit));
        $this->assertSame('select authors.*, (select count(*) from posts where authors.id = posts.author_id) as @aggregate_0 from authors where authors.deleted_at is null', str_replace(['"', '`'], '', $aggregate->toRawSql()));
    }

    /** @param Closure(Resource): mixed $plan */
    #[Test]
    #[DataProvider('foreignNodes')]
    public function it_refuses_a_node_of_another_grammar(Closure $plan, string $node): void
    {
        $resource = $this->context()->resource(PostResource::class);

        $this->expectExceptionObject(new LogicException($node.' does not apply to a database query.'));

        $plan($resource);
    }

    /** @return iterable<string, array{Closure(Resource): mixed, class-string}> */
    public static function foreignNodes(): iterable
    {
        yield 'an engine filter' => [
            static fn (): FilterGroupClause => FilterGroupClause::of(new FilterGroup([
                new EngineFilterCondition('price', new IndexAttribute(Integer::make('price'), [FilterOperator::Equal]), FilterOperator::Equal, 1),
            ])),
            EngineFilterCondition::class,
        ];
        yield 'an engine sort' => [
            static fn (Resource $resource): array => (new SqlRows(null))->clauses($resource, self::sorted(new EngineSort('price', null))),
            EngineSort::class,
        ];
    }

    #[Test]
    public function it_refuses_the_relevance_order_without_a_text(): void
    {
        $resource = $this->context()->resource(PostResource::class);

        $this->expectExceptionObject(new InvalidFieldException('The field [@relevance] is not sortable.'));

        (new SqlRows(null))->clauses($resource, self::sorted(new RelevanceSort(null)));
    }

    private static function sorted(SortNode $sort): SearchNode
    {
        return new SearchNode(null, null, new FilterGroup, [$sort], null, [], [], [], [], null, null, null);
    }

    /** @param array<string, mixed> $search */
    private function paged(Resource $resource, array $search): QueryPlan
    {
        return SearchPlanner::paged($resource, $this->parse($resource, $search), $resource->context()->text($resource));
    }

    /** @param array<string, mixed> $search */
    private function all(Resource $resource, array $search): QueryPlan
    {
        return SearchPlanner::all($resource, $this->parse($resource, $search), $resource->context()->text($resource));
    }

    private function limited(LimitPolicy $limit): string
    {
        $query = Post::query();

        $limit->apply($query);

        return str_replace(['"', '`'], '', $query->toRawSql());
    }

    /** @param class-string<Resource> $class */
    private function listed(string $class): Resource
    {
        $context = $this->context();
        $resource = $context->resource($class);

        $context->authorizer()->plan([Check::onClass($resource, 'viewAny')]);

        return $resource;
    }

    private function context(): RestContext
    {
        return $this->context ??= new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);
    }
}
