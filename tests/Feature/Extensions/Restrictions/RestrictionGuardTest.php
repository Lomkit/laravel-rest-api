<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ClausedExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedAuthorController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedAuthorResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['claused' => ClausedExtension::class])]
class RestrictionGuardTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RestrictionLog::reset();
        ClausedExtension::$clause = null;
        Rest::resource('restricted-authors', RestrictedAuthorController::class)->register();
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1', 'deleted_at' => null],
            ['id' => 2, 'first_name' => 'Alan', 'last_name' => 'Turing', 'api_token' => 'token-2', 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        RestrictionLog::reset();
        ClausedExtension::$clause = null;

        parent::tearDown();
    }

    /** @param Closure(Builder<Model>): mixed $clause */
    #[Test]
    #[DataProvider('clauses')]
    public function it_refuses_a_restriction_that_holds_more_than_where_clauses(Closure $clause, QueryPart $part): void
    {
        ClausedExtension::$clause = $clause;
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionClause('claused', RestrictedAuthorResource::class, $part));

        $this->postJson('/restricted-authors/search', ['search' => []]);
    }

    /** @return array<string, array{Closure(Builder<Model>): mixed, QueryPart}> */
    public static function clauses(): array
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
            'an order two groups down' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where(static fn (Builder $deeper): Builder => $deeper->where('first_name', 'Ada')->orderBy('id'))), QueryPart::Order],
            'an order in a nested base group' => [static fn (Builder $query): QueryBuilder => $query->getQuery()->where(static fn (QueryBuilder $nested): QueryBuilder => $nested->where('first_name', 'Ada')->orderBy('id')), QueryPart::Order],
            'an after query callback in a nested group' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): QueryBuilder => $nested->where('first_name', 'Ada')->getQuery()->afterQuery(static fn (mixed $rows): mixed => $rows)), QueryPart::AfterCallback],
        ];
    }

    #[Test]
    public function it_refuses_a_restriction_that_removes_a_global_scope(): void
    {
        ClausedExtension::$clause = static fn (Builder $query): Builder => $query->withoutGlobalScope(SoftDeletingScope::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionRemovesScopes('claused', RestrictedAuthorResource::class, [SoftDeletingScope::class]));

        $this->postJson('/restricted-authors/search', ['search' => []]);
    }

    #[Test]
    public function it_names_the_extension_and_the_resource_and_advises_another_table(): void
    {
        $exception = InvalidQueryExtensionException::restrictionClause('claused', RestrictedAuthorResource::class, QueryPart::Join);

        $this->assertSame('The [claused] extension adds a join to the restriction of '.RestrictedAuthorResource::class.', which holds only where clauses. Restrict through another table with whereHas() or whereExists().', $exception->getMessage());
        $this->assertTrue($exception->namesExtension('claused'));
    }
}
