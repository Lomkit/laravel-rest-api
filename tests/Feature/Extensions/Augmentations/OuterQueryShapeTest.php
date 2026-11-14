<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Query\OuterQueryShape;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Search\Plan\AddedColumns;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OuterQueryShapeTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @param Closure(Builder<Model>): mixed $change */
    #[Test]
    #[DataProvider('changes')]
    public function it_names_the_first_part_that_changed(Closure $change, QueryPart $part): void
    {
        $query = Author::query()->select('authors.id');
        $before = OuterQueryShape::of($query);

        $change($query);

        $this->assertSame($part, OuterQueryShape::of($query)->changedFrom($before));
    }

    /** @return array<string, array{Closure(Builder<Model>): mixed, QueryPart}> */
    public static function changes(): array
    {
        return [
            'a where' => [static fn (Builder $query): Builder => $query->where('id', 1), QueryPart::Where],
            'a join' => [static fn (Builder $query): Builder => $query->join('posts', 'posts.author_id', '=', 'authors.id'), QueryPart::Join],
            'an order' => [static fn (Builder $query): Builder => $query->orderBy('id'), QueryPart::Order],
            'a union order' => [static fn (Builder $query): mixed => $query->getQuery()->unionOrders = [['column' => 'id', 'direction' => 'asc']], QueryPart::Order],
            'a group' => [static fn (Builder $query): Builder => $query->groupBy('id'), QueryPart::Group],
            'a having' => [static fn (Builder $query): Builder => $query->havingRaw('count(*) > 1'), QueryPart::Having],
            'a union' => [static fn (Builder $query): Builder => $query->union(Author::query()->select('id')), QueryPart::Union],
            'a limit' => [static fn (Builder $query): Builder => $query->limit(1), QueryPart::Limit],
            'a group limit' => [static fn (Builder $query): Builder => $query->groupLimit(1, 'id'), QueryPart::Limit],
            'an offset' => [static fn (Builder $query): Builder => $query->offset(1), QueryPart::Offset],
            'a lock' => [static fn (Builder $query): Builder => $query->lockForUpdate(), QueryPart::Lock],
            'distinct' => [static fn (Builder $query): Builder => $query->distinct(), QueryPart::Distinct],
            'another table' => [static fn (Builder $query): Builder => $query->from('posts'), QueryPart::From],
            'a before query callback' => [static fn (Builder $query): Builder => $query->beforeQuery(static function (QueryBuilder $query): void {}), QueryPart::Callback],
            'a removed scope' => [static fn (Builder $query): Builder => $query->withoutGlobalScope(SoftDeletingScope::class), QueryPart::RemovedScope],
        ];
    }

    #[Test]
    public function it_sees_no_change_from_added_columns_subqueries_and_eager_loads(): void
    {
        $query = Author::query()->select('authors.id');
        $before = OuterQueryShape::of($query);

        AddedColumns::merge($query, ['api_token', 'id']);
        $query->with(['drafts' => static function (): void {}]);
        $query->addSelect(['extra' => static function (QueryBuilder $subquery): void {
            $subquery->selectRaw('?', [1]);
        }]);

        $this->assertNull(OuterQueryShape::of($query)->changedFrom($before));
        $this->assertSame('select authors.id, authors.api_token, (select ?) as extra from authors where authors.deleted_at is null', $this->sql($query));
    }

    #[Test]
    public function it_adds_no_column_to_a_query_that_reads_every_column(): void
    {
        $every = Author::query();
        $starred = Author::query()->select('authors.*');

        AddedColumns::merge($every, ['api_token']);
        AddedColumns::merge($starred, ['api_token']);

        $this->assertSame([null, ['authors.*']], [$every->getQuery()->columns, $starred->getQuery()->columns]);
    }

    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}
