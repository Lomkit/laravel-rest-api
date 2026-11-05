<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\UnplannedCheckException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Tests\Feature\Search\Stubs\OpenTeamResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PublicTagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Publishing\DraftResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('database.connections.pgsql_grammar', ['driver' => 'pgsql', 'database' => ':memory:', 'prefix' => ''])]
#[WithConfig('database.connections.mysql_grammar', ['driver' => 'mysql', 'database' => ':memory:', 'prefix' => ''])]
#[WithConfig('database.connections.sqlite_grammar', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''])]
class NestedAggregateTest extends PackageTestCase
{
    private RestContext $context;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->context = new RestContext(RestRequest::create('/api/teams/search', 'POST'), $this->app);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_counts_the_rows_at_the_end_of_a_path_once(string $connection, string $sql): void
    {
        $query = Team::on($connection);

        $this->planned()->apply($query);

        $this->assertSame($sql, $query->toSql());
    }

    /** @return array<string, array{string, string}> */
    public static function drivers(): array
    {
        return [
            'sqlite' => ['sqlite_grammar', 'select "teams".*, (select count(*) from "tags" as "@rows" where exists (select * from "tickets" where "teams"."id" = "tickets"."team_id" and ("tickets"."status" != ?) and exists (select * from "tags" inner join "tag_ticket" on "tags"."id" = "tag_ticket"."tag_id" where "tickets"."id" = "tag_ticket"."ticket_id" and "tags"."id" = "@rows"."id" and (("tags"."name" != ?)) and "tags"."deleted_at" is null))) as "@aggregate_0" from "teams"'],
            'mysql' => ['mysql_grammar', 'select `teams`.*, (select count(*) from `tags` as `@rows` where exists (select * from `tickets` where `teams`.`id` = `tickets`.`team_id` and (`tickets`.`status` != ?) and exists (select * from `tags` inner join `tag_ticket` on `tags`.`id` = `tag_ticket`.`tag_id` where `tickets`.`id` = `tag_ticket`.`ticket_id` and `tags`.`id` = `@rows`.`id` and ((`tags`.`name` != ?)) and `tags`.`deleted_at` is null))) as `@aggregate_0` from `teams`'],
            'pgsql' => ['pgsql_grammar', 'select "teams".*, (select count(*) from "tags" as "@rows" where exists (select * from "tickets" where "teams"."id" = "tickets"."team_id" and ("tickets"."status" != ?) and exists (select * from "tags" inner join "tag_ticket" on "tags"."id" = "tag_ticket"."tag_id" where "tickets"."id" = "tag_ticket"."ticket_id" and "tags"."id" = "@rows"."id" and (("tags"."name" != ?)) and "tags"."deleted_at" is null))) as "@aggregate_0" from "teams"'],
        ];
    }

    #[Test]
    public function it_tests_whether_a_path_reaches_a_row(): void
    {
        $query = Team::on('sqlite_grammar');

        $this->planned(AggregateFunction::Exists)->apply($query);

        $this->assertSame('select "teams".*, exists(select * from "tags" as "@rows" where exists (select * from "tickets" where "teams"."id" = "tickets"."team_id" and ("tickets"."status" != ?) and exists (select * from "tags" inner join "tag_ticket" on "tags"."id" = "tag_ticket"."tag_id" where "tickets"."id" = "tag_ticket"."ticket_id" and "tags"."id" = "@rows"."id" and (("tags"."name" != ?)) and "tags"."deleted_at" is null))) as "@aggregate_0" from "teams"', $query->toSql());
        $this->assertSame(['closed', 'Secret'], $query->getBindings());
    }

    #[Test]
    public function it_orders_by_the_subquery_of_a_path(): void
    {
        $query = Team::on('sqlite_grammar');

        $this->planned()->order($query, SortDirection::Desc);

        $this->assertSame('select * from "teams" order by (select count(*) from "tags" as "@rows" where exists (select * from "tickets" where "teams"."id" = "tickets"."team_id" and ("tickets"."status" != ?) and exists (select * from "tags" inner join "tag_ticket" on "tags"."id" = "tag_ticket"."tag_id" where "tickets"."id" = "tag_ticket"."ticket_id" and "tags"."id" = "@rows"."id" and (("tags"."name" != ?)) and "tags"."deleted_at" is null))) desc', $query->toSql());
        $this->assertSame(['closed', 'Secret'], $query->getBindings());
    }

    #[Test]
    public function it_orders_by_whether_a_path_reaches_a_row(): void
    {
        $query = Team::on('sqlite_grammar');

        $this->planned(AggregateFunction::Exists)->order($query, SortDirection::Desc);

        $this->assertSame('select * from "teams" order by exists(select * from "tags" as "@rows" where exists (select * from "tickets" where "teams"."id" = "tickets"."team_id" and ("tickets"."status" != ?) and exists (select * from "tags" inner join "tag_ticket" on "tags"."id" = "tag_ticket"."tag_id" where "tickets"."id" = "tag_ticket"."ticket_id" and "tags"."id" = "@rows"."id" and (("tags"."name" != ?)) and "tags"."deleted_at" is null))) desc', $query->toSql());
        $this->assertSame(['closed', 'Secret'], $query->getBindings());
    }

    #[Test]
    public function it_reads_a_uuid_key_at_the_end_of_a_path_as_text_on_postgresql(): void
    {
        $query = Post::on('pgsql_grammar');
        $posts = $this->context->resource(PostResource::class);
        $drafts = $this->context->resource(DraftResource::class);
        $author = $posts->definition()->relation('author');
        $this->context->authorizer()->plan([Check::onClass($author->resource(), 'viewAny')]);
        $field = ID::make('id')->keyType('uuid');
        $node = new AggregateNode('author.drafts', $drafts, AggregateFunction::Max, $field, null, AggregateKey::of('author.drafts', AggregateFunction::Max, $field, null), new FilterGroup, [$author]);

        AggregateClause::of($node, QueryPlan::bare($drafts), 0)->apply($query);

        $this->assertSame('select "posts".*, (select max("@rows"."id"::text) from "drafts" as "@rows" where exists (select * from "authors" where "posts"."author_id" = "authors"."id" and exists (select * from "drafts" where "authors"."id" = "drafts"."author_id" and "drafts"."id" = "@rows"."id") and "authors"."deleted_at" is null)) as "@aggregate_0" from "posts"', $query->toSql());
    }

    #[Test]
    public function it_refuses_to_apply_the_perimeter_of_a_level_that_no_check_planned(): void
    {
        $this->expectException(UnplannedCheckException::class);

        $this->clause(AggregateFunction::Count)->apply(Team::on('sqlite_grammar'));
    }

    private function planned(AggregateFunction $function = AggregateFunction::Count): AggregateClause
    {
        $tickets = $this->context
            ->resource(OpenTeamResource::class)
            ->definition()
            ->relation('tickets');
        $this->context->authorizer()->plan([Check::onClass($tickets->resource(), 'viewAny')]);

        return $this->clause($function);
    }

    private function clause(AggregateFunction $function, ?Field $field = null): AggregateClause
    {
        $tickets = $this->context
            ->resource(OpenTeamResource::class)
            ->definition()
            ->relation('tickets');
        $tags = $this->context->resource(PublicTagResource::class);
        $node = new AggregateNode('tickets.tags', $tags, $function, $field, null, AggregateKey::of('tickets.tags', $function, $field, null), new FilterGroup, [$tickets]);

        return AggregateClause::of($node, new QueryPlan($tags, [new PerimeterClause($tags)], [], [], LimitPolicy::none()), 0);
    }
}
