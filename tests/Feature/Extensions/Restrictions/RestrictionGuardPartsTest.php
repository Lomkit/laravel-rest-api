<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Query\RestrictionGuard;
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

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionClause('claused', RestrictedAuthorResource::class, $part));

        RestrictionGuard::ensureOnlyWheres($query, 'claused', new RestrictedAuthorResource);
    }

    /** @return array<string, array{Closure(Builder<Model>): mixed, QueryPart}> */
    public static function parts(): array
    {
        return [
            ...RestrictionGuardTest::clauses(),
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

        RestrictionGuard::ensureOnlyWheres($query, 'claused', new RestrictedAuthorResource);

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
