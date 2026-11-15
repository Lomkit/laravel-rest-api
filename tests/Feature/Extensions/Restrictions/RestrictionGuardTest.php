<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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

#[WithConfig('rest.extensions', [ClausedExtension::class])]
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

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionClause('claused', ClausedExtension::class, RestrictedAuthorResource::class, $part));

        $this->postJson('/restricted-authors/search', ['search' => []]);
    }

    /** @return array<string, array{Closure(Builder<Model>): mixed, QueryPart}> */
    public static function clauses(): array
    {
        return [
            'a join' => [static fn (Builder $query): Builder => $query->join('posts', 'posts.author_id', '=', 'authors.id'), QueryPart::Join],
            'an eager load two groups down' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where(static fn (Builder $deeper): Builder => $deeper->where('first_name', 'Ada')->with('posts'))), QueryPart::EagerLoad],
        ];
    }

    /** @param Closure(Builder<Model>): Builder<Model> $clause */
    #[Test]
    #[DataProvider('scopeRemovals')]
    public function it_refuses_a_restriction_that_removes_a_global_scope(Closure $clause): void
    {
        ClausedExtension::$clause = $clause;
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionRemovesScopes('claused', ClausedExtension::class, RestrictedAuthorResource::class, [SoftDeletingScope::class]));

        $this->postJson('/restricted-authors/search', ['search' => []]);
    }

    /** @return array<string, array{Closure(Builder<Model>): Builder<Model>}> */
    public static function scopeRemovals(): array
    {
        return [
            'at the top level' => [static fn (Builder $query): Builder => $query->withoutGlobalScope(SoftDeletingScope::class)],
            'two groups down' => [static fn (Builder $query): Builder => $query->where(static fn (Builder $nested): Builder => $nested->where(static fn (Builder $deeper): Builder => $deeper->withTrashed()->where('first_name', 'Ada')))],
        ];
    }

    #[Test]
    public function it_names_the_registered_class_of_an_extension_bound_to_a_subclass(): void
    {
        $this->app?->bind(ClausedExtension::class, static fn (): ClausedExtension => new class extends ClausedExtension {});
        ClausedExtension::$clause = static fn (Builder $query): Builder => $query->orderBy('id');
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/restricted-authors/search', ['search' => []]);
            $this->fail('The order was accepted.');
        } catch (InvalidQueryExtensionException $exception) {
            $this->assertSame(InvalidQueryExtensionException::restrictionClause('claused', ClausedExtension::class, RestrictedAuthorResource::class, QueryPart::Order)->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(ClausedExtension::class));
        }
    }

    #[Test]
    public function it_names_the_extension_and_the_resource_and_advises_another_table(): void
    {
        $exception = InvalidQueryExtensionException::restrictionClause('claused', ClausedExtension::class, RestrictedAuthorResource::class, QueryPart::Join);

        $this->assertSame('The [claused] extension '.ClausedExtension::class.' adds a join to the restriction of '.RestrictedAuthorResource::class.', which holds only where clauses. Restrict through another table with whereHas() or whereExists().', $exception->getMessage());
        $this->assertSame([true, false], [$exception->concerns(ClausedExtension::class), $exception->concerns('claused')]);
    }
}
