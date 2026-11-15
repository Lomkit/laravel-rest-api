<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Query\RestrictionGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ClausedExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedAuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RestrictionGuardPartsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @param Closure(Builder<Model>): mixed $clause */
    #[Test]
    #[DataProvider('parts')]
    public function it_names_the_first_refused_part(Closure $clause, QueryPart $part): void
    {
        $query = Author::query();
        $clause($query);

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionClause('claused', ClausedExtension::class, RestrictedAuthorResource::class, $part));

        RestrictionGuard::ensureOnlyWheres($query, 'claused', ClausedExtension::class, new RestrictedAuthorResource);
    }

    /** @return array<string, array{Closure(Builder<Model>): mixed, QueryPart}> */
    public static function parts(): array
    {
        return [
            'a join' => [static fn (Builder $query): Builder => $query->join('posts', 'posts.author_id', '=', 'authors.id'), QueryPart::Join],
            'an order' => [static fn (Builder $query): Builder => $query->orderBy('first_name'), QueryPart::Order],
            'a limit' => [static fn (Builder $query): Builder => $query->limit(1), QueryPart::Limit],
            'an offset' => [static fn (Builder $query): Builder => $query->offset(1), QueryPart::Offset],
            'selected columns' => [static fn (Builder $query): Builder => $query->select('id'), QueryPart::Columns],
            'distinct' => [static fn (Builder $query): Builder => $query->distinct(), QueryPart::Distinct],
            'a lock' => [static fn (Builder $query): Builder => $query->lockForUpdate(), QueryPart::Lock],
            'a group by' => [static fn (Builder $query): Builder => $query->groupBy('first_name'), QueryPart::Group],
            'a having' => [static fn (Builder $query): Builder => $query->havingRaw('count(*) > 1'), QueryPart::Having],
            'a union' => [static fn (Builder $query): Builder => $query->union(DB::table('authors')->select('*')), QueryPart::Union],
            'an eager load' => [static fn (Builder $query): Builder => $query->with('posts'), QueryPart::EagerLoad],
            'another table' => [static fn (Builder $query): Builder => $query->from('posts'), QueryPart::From],
            'a before query callback' => [static fn (Builder $query): Builder => $query->beforeQuery(static function (QueryBuilder $query): void {}), QueryPart::Callback],
            'a clause added last' => [static fn (Builder $query): Builder => $query->where('first_name', 'Ada')->orderBy('id'), QueryPart::Order],
            'a global scope' => [static fn (Builder $query): Builder => $query->withGlobalScope('nothing', static fn (Builder $query): Builder => $query->whereRaw('0 = 1')), QueryPart::Scope],
            'a replaced global scope' => [static fn (Builder $query): Builder => $query->withGlobalScope(SoftDeletingScope::class, static function (Builder $query): void {}), QueryPart::Scope],
            'an after query callback' => [static fn (Builder $query): Builder => $query->afterQuery(static fn (mixed $rows): mixed => $rows), QueryPart::AfterCallback],
            'an after query callback on the base query' => [static fn (Builder $query): QueryBuilder => $query->getQuery()->afterQuery(static fn (mixed $rows): mixed => $rows), QueryPart::AfterCallback],
            'a join in a nested group' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where('first_name', 'Ada')->join('posts', 'posts.author_id', '=', 'authors.id')), QueryPart::Join],
            'an order in a nested group' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where('first_name', 'Ada')->orderBy('id')), QueryPart::Order],
            'a limit in a nested group' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where('first_name', 'Ada')->limit(1)), QueryPart::Limit],
            'another table in a nested group' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where('first_name', 'Ada')->from('posts')), QueryPart::From],
            'an eager load two groups down' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where(static fn (Builder $deeper): Builder => $deeper->where('first_name', 'Ada')->with('posts'))), QueryPart::EagerLoad],
            'an order two groups down' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where(static fn (Builder $deeper): Builder => $deeper->where('first_name', 'Ada')->orderBy('id'))), QueryPart::Order],
            'an order in a nested base group' => [static fn (Builder $query): QueryBuilder => $query->getQuery()->where(static fn (QueryBuilder $nested): QueryBuilder => $nested->where('first_name', 'Ada')->orderBy('id')), QueryPart::Order],
            'an after query callback in a nested group' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): QueryBuilder => $nested->where('first_name', 'Ada')->getQuery()->afterQuery(static fn (mixed $rows): mixed => $rows)), QueryPart::AfterCallback],
            'a union order' => [static fn (Builder $query): mixed => $query->getQuery()->unionOrders = [['column' => 'id', 'direction' => 'asc']], QueryPart::Order],
            'a union limit' => [static fn (Builder $query): mixed => $query->getQuery()->unionLimit = 1, QueryPart::Limit],
            'a union offset' => [static fn (Builder $query): mixed => $query->getQuery()->unionOffset = 1, QueryPart::Offset],
            'a group limit' => [static fn (Builder $query): Builder => $query->groupLimit(1, 'id'), QueryPart::Limit],
            'a raw column' => [static fn (Builder $query): Builder => $query->selectRaw('1'), QueryPart::Columns],
            'a shared lock' => [static fn (Builder $query): Builder => $query->sharedLock(), QueryPart::Lock],
        ];
    }

    /** @param Closure(Builder<Model>): mixed $clause */
    #[Test]
    #[DataProvider('wheres')]
    public function it_accepts_where_clauses_of_every_form(Closure $clause): void
    {
        $query = Author::query();
        $clause($query);

        RestrictionGuard::ensureOnlyWheres($query, 'claused', ClausedExtension::class, new RestrictedAuthorResource);

        $this->assertNotSame([], $query->getQuery()->wheres);
    }

    /** @return array<string, array{Closure(Builder<Model>): mixed}> */
    public static function wheres(): array
    {
        return [
            'a where' => [static fn (Builder $query): Builder => $query->where('first_name', 'Ada')],
            'an or where' => [static fn (Builder $query): Builder => $query->orWhere('first_name', 'Ada')],
            'a where in' => [static fn (Builder $query): Builder => $query->whereIn('id', [1, 2])],
            'a where null' => [static fn (Builder $query): Builder => $query->whereNull('settings')],
            'a where has' => [static fn (Builder $query): Builder => $query->whereHas('posts')],
            'a where exists' => [static fn (Builder $query): Builder => $query->whereExists(static function (QueryBuilder $posts): void {
                $posts->from('posts')->whereColumn('posts.author_id', 'authors.id');
            })],
            'a raw where' => [static fn (Builder $query): Builder => $query->whereRaw('0 = 1')],
            'a nested where' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where('id', 1)->orWhere('id', 2))],
            'a where exists that joins another table' => [static fn (Builder $query): Builder => $query->whereExists(static function (QueryBuilder $posts): void {
                $posts->from('posts')->join('drafts', 'drafts.post_id', '=', 'posts.id')->whereColumn('posts.author_id', 'authors.id')->orderBy('posts.id')->limit(1);
            })],
            'a nested where two groups down' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where(static fn (Builder $deeper): Builder => $deeper->where('id', 1))->orWhere('id', 2))],
            'a where on a qualified column' => [static fn (Builder $query): Builder => $query->where($query->qualifyColumn('last_name'), '!=', 'Hidden')],
        ];
    }
}
