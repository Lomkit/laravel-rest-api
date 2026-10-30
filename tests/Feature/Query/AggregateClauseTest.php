<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Tests\Feature\Query\Stubs\Folder;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateClauseTest extends PackageTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (['pgsql', 'mysql', 'sqlite'] as $driver) {
            $app['config']->set("database.connections.{$driver}_grammar", ['driver' => $driver, 'database' => ':memory:', 'prefix' => '']);
        }
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('uuidKeys')]
    public function it_reads_a_uuid_key_as_text_on_postgresql(Closure $field): void
    {
        $query = Author::on('pgsql_grammar');

        (new AggregateClause('drafts', AggregateFunction::Max, $field()))->apply($query);

        $this->assertSame(
            'select "authors".*, (select max("drafts"."id"::text) from "drafts" where "authors"."id" = "drafts"."author_id") as "drafts_max_id" from "authors" where "authors"."deleted_at" is null',
            $query->toSql(),
        );
    }

    /** @return array<string, array{Closure(): Field}> */
    public static function uuidKeys(): array
    {
        return [
            'declared uuid key' => [fn (): Field => ID::make('id')->keyType('uuid')],
            'key of a model with uuids' => [fn (): Field => FieldSet::of([ID::make('id')])->bindTo(new Draft)->get('id')],
        ];
    }

    #[Test]
    #[DataProvider('otherDrivers')]
    public function it_reads_the_column_of_a_uuid_key_on_the_other_drivers(string $connection, string $expected): void
    {
        $query = Author::on($connection);

        (new AggregateClause('drafts', AggregateFunction::Min, ID::make('id')->keyType('uuid')))->apply($query);

        $this->assertSame($expected, $query->toSql());
    }

    /** @return array<string, array{string, string}> */
    public static function otherDrivers(): array
    {
        return [
            'mysql' => ['mysql_grammar', 'select `authors`.*, (select min(`drafts`.`id`) from `drafts` where `authors`.`id` = `drafts`.`author_id`) as `drafts_min_id` from `authors` where `authors`.`deleted_at` is null'],
            'sqlite' => ['sqlite_grammar', 'select "authors".*, (select min("drafts"."id") from "drafts" where "authors"."id" = "drafts"."author_id") as "drafts_min_id" from "authors" where "authors"."deleted_at" is null'],
        ];
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('otherFields')]
    public function it_reads_the_column_of_any_other_field_on_postgresql(Closure $field, string $column): void
    {
        $query = Author::on('pgsql_grammar');

        (new AggregateClause('drafts', AggregateFunction::Max, $field()))->apply($query);

        $this->assertSame(
            "select \"authors\".*, (select max(\"drafts\".\"{$column}\") from \"drafts\" where \"authors\".\"id\" = \"drafts\".\"author_id\") as \"drafts_max_{$column}\" from \"authors\" where \"authors\".\"deleted_at\" is null",
            $query->toSql(),
        );
    }

    /** @return array<string, array{Closure(): Field, string}> */
    public static function otherFields(): array
    {
        return [
            'integer key' => [fn (): Field => ID::make('id'), 'id'],
            'ulid key' => [fn (): Field => ID::make('id')->keyType('ulid'), 'id'],
            'string key' => [fn (): Field => ID::make('id')->keyType('string'), 'id'],
            'text' => [fn (): Field => Text::make('title'), 'title'],
        ];
    }

    #[Test]
    public function it_names_a_uuid_aggregate_after_the_alias_on_postgresql(): void
    {
        $query = Author::on('pgsql_grammar');

        (new AggregateClause('drafts', AggregateFunction::Max, ID::make('id')->keyType('uuid'), 'latest_draft'))->apply($query);

        $this->assertSame(
            'select "authors".*, (select max("drafts"."id"::text) from "drafts" where "authors"."id" = "drafts"."author_id") as "latest_draft" from "authors" where "authors"."deleted_at" is null',
            $query->toSql(),
        );
    }

    #[Test]
    public function it_reads_the_uuid_key_of_a_self_relation_through_its_alias_on_postgresql(): void
    {
        $query = Folder::on('pgsql_grammar');

        (new AggregateClause('children', AggregateFunction::Min, ID::make('id')->keyType('uuid')))->apply($query);

        $this->assertMatchesRegularExpression(
            '/^select "folders"\.\*, \(select min\("(laravel_reserved_\d+)"\."id"::text\) from "folders" as "\1" where "folders"\."id" = "\1"\."parent_id"\) as "children_min_id" from "folders"$/',
            $query->toSql(),
        );
    }

    #[Test]
    public function it_aggregates_on_the_query_of_a_relation(): void
    {
        $query = Relation::noConstraints(fn (): BelongsTo => (new Post)->author());

        (new AggregateClause('drafts', AggregateFunction::Count))->apply($query);

        $this->assertSame(
            'select authors.*, (select count(*) from drafts where authors.id = drafts.author_id) as drafts_count from authors where authors.deleted_at is null',
            $this->sql($query),
        );
    }

    /** @param array{relation: string, type: string, field?: string, alias?: string} $aggregate */
    #[Test]
    #[DataProvider('aggregateEntries')]
    public function it_resolves_an_aggregate_entry_against_the_fields(array $aggregate, string $expected): void
    {
        $query = Author::query();
        $fields = FieldSet::of([ID::make('id'), Integer::make('views')]);

        AggregateClause::fromAggregate($fields, $aggregate, fn (Builder $query) => $query->where('views', '>', 10))->apply($query);

        $this->assertSame($expected, $this->sql($query));
    }

    /** @return array<string, array{array{relation: string, type: string, field?: string, alias?: string}, string}> */
    public static function aggregateEntries(): array
    {
        $from = 'from posts where authors.id = posts.author_id and views > ?)';

        return [
            'a field' => [['relation' => 'posts', 'type' => 'sum', 'field' => 'views'], "select authors.*, (select sum(posts.views) {$from} as posts_sum_views from authors where authors.deleted_at is null"],
            'an alias' => [['relation' => 'posts', 'type' => 'max', 'field' => 'id', 'alias' => 'last_post'], "select authors.*, (select max(posts.id) {$from} as last_post from authors where authors.deleted_at is null"],
            'a field on a function that takes none' => [['relation' => 'posts', 'type' => 'exists', 'field' => 'views'], "select authors.*, exists(select * {$from} as posts_exists from authors where authors.deleted_at is null"],
        ];
    }

    #[Test]
    public function it_refuses_an_aggregate_entry_on_an_undeclared_field(): void
    {
        $this->expectExceptionObject(new InvalidFieldException('Unknown field [rating].'));

        AggregateClause::fromAggregate(FieldSet::of([Integer::make('views')]), ['relation' => 'posts', 'type' => 'avg', 'field' => 'rating'], fn (Builder $query) => $query);
    }

    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}
