<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Search\Plan\AliasedIncludeClause;
use Lomkit\Rest\Search\Plan\DefaultOrderClause;
use Lomkit\Rest\Search\Plan\IncludeClause;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\SearchPlanner;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchPlannerIncludesTest extends PackageTestCase
{
    use ParsesSearchNodes;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_expands_a_dotted_include(): void
    {
        $published = ['relation' => 'posts', 'filters' => [['field' => 'status', 'value' => 'published']]];
        $author = [IncludeClause::class, 'author', [PerimeterClause::class, DefaultOrderClause::class], []];
        $merged = [[IncludeClause::class, 'posts', [PerimeterClause::class, FilterGroupClause::class, DefaultOrderClause::class], [$author]]];
        $dotted = $this->includes([['relation' => 'posts.author']]);

        $this->assertSame([[IncludeClause::class, 'posts', [], [$author]]], self::tree($dotted));
        $this->assertInstanceOf(IncludeClause::class, $dotted[0]);
        $this->assertEquals(LimitPolicy::none(), $dotted[0]->plan->limit);
        $this->assertSame($merged, self::tree($this->includes([$published, ['relation' => 'posts.author']])));
        $this->assertSame($merged, self::tree($this->includes([['relation' => 'posts.author'], $published])));
        $this->assertSame(
            [
                [AliasedIncludeClause::class, 'posts', [PerimeterClause::class, DefaultOrderClause::class], []],
                [IncludeClause::class, 'posts', [], [$author]],
            ],
            self::tree($this->includes([['relation' => 'posts', 'alias' => 'drafted'], ['relation' => 'posts.author']])),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $includes
     * @return list<Clause>
     */
    private function includes(array $includes): array
    {
        $context = new RestContext(RestRequest::create('/api/authors/search', 'POST'), $this->app);
        $authors = $context->resource(AuthorResource::class);

        $context->authorizer()->plan([
            Check::onClass($authors, 'viewAny'),
            Check::onClass($context->resource(PostResource::class), 'viewAny'),
        ]);

        return SearchPlanner::paged($authors, $this->parse($authors, ['includes' => $includes]), $context->text($authors))->includes;
    }

    /**
     * @param  list<Clause>  $includes
     * @return list<list<mixed>>
     */
    private static function tree(array $includes): array
    {
        return array_map(static function (Clause $include): array {
            if (! $include instanceof IncludeClause && ! $include instanceof AliasedIncludeClause) {
                return [$include::class];
            }

            return [$include::class, $include->relation, array_map(get_class(...), $include->plan->clauses), self::tree($include->plan->includes)];
        }, $includes);
    }
}
