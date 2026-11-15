<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Auth\GenericUser;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EarlyRedRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuestlessExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\LateRedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\NoSqlDatabaseProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SqlForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignProfile;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RestrictingForeignProfile;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\RowRestriction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class RestrictedEngineSearchTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RestrictionLog::reset();
        ForeignProfile::reset();
        SqlForeignEngineProfiles::$skipsRows = false;
    }

    #[\Override]
    protected function tearDown(): void
    {
        RestrictionLog::reset();
        ForeignProfile::reset();
        SqlForeignEngineProfiles::$skipsRows = false;

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_a_text_search_through_an_engine_that_applies_no_sql_restriction(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class, HiddenRowsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unrestrictedEngine(VoucherResource::class, 'hidden', HiddenRowsExtension::class, ForeignEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_points_the_disabled_extensions_to_the_registered_class_of_a_bound_subclass(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class, HiddenRowsExtension::class]]);
        $this->app?->bind(HiddenRowsExtension::class, static fn (): HiddenRowsExtension => new class extends HiddenRowsExtension {});
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unrestrictedEngine(VoucherResource::class, 'hidden', HiddenRowsExtension::class, ForeignEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_refuses_an_action_on_the_results_of_such_a_search(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class, HiddenRowsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unrestrictedEngine(VoucherResource::class, 'hidden', HiddenRowsExtension::class, ForeignEngine::class));

        $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);
    }

    #[Test]
    public function it_names_the_extension_the_engine_and_the_ways_out(): void
    {
        $this->assertSame(
            'The [hidden] extension '.HiddenRowsExtension::class.' restricts the rows of '.VoucherResource::class.' in SQL, but the text search of '.VoucherResource::class.' runs on ['.ForeignEngine::class.'], which does not apply SQL restrictions. Implement Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries in '.HiddenRowsExtension::class.' to restrict the engine search too, index the model with the Scout database or collection driver, leave '.VoucherResource::class.' out of the extension with appliesTo(), or add '.HiddenRowsExtension::class.' to the [rest.disabled_extensions] config.',
            InvalidTextSearchException::unrestrictedEngine(VoucherResource::class, 'hidden', HiddenRowsExtension::class, ForeignEngine::class)->getMessage(),
        );
    }

    #[Test]
    public function it_still_searches_without_text_on_a_restricted_resource(): void
    {
        config(['rest.extensions' => [HiddenRowsExtension::class]]);

        $response = $this->search('vouchers', ['filters' => [['field' => 'title', 'value' => 'Budget']]]);

        $response->assertOk()->assertJsonPath('data.*.title', ['Budget']);
        $this->assertSame(['VoucherResource guest'], RestrictionLog::$seen);
    }

    #[Test]
    public function it_still_searches_text_with_an_extension_of_another_kind(): void
    {
        config(['rest.extensions' => [NotingExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
    }

    #[Test]
    public function it_accepts_a_restriction_that_also_restricts_the_engine(): void
    {
        config(['rest.extensions' => [RedEverywhereExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
    }

    #[Test]
    public function it_names_the_first_unreached_extension_by_name_in_any_order(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [HiddenRowsExtension::class, LateRedEverywhereExtension::class, EarlyRedRowsExtension::class, ForeignEngineProfiles::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unrestrictedEngine(VoucherResource::class, 'a', EarlyRedRowsExtension::class, ForeignEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_refuses_the_database_engine_when_its_profile_applies_no_sql_restriction(): void
    {
        config(['scout.driver' => 'database', 'rest.extensions' => [HiddenRowsExtension::class, NoSqlDatabaseProfiles::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unrestrictedEngine(VoucherResource::class, 'hidden', HiddenRowsExtension::class, DatabaseEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_hands_the_restrictions_to_a_profile_that_applies_them_on_another_engine(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [GuestlessExtension::class, SqlForeignEngineProfiles::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 1));
        $this->assertInstanceOf(RowRestriction::class, ForeignProfile::$pages[0][2]);
        $this->assertSame(['select * from vouchers where (0 = 1) and vouchers.deleted_at is null'], array_map(fn (string $sql): string => str_replace(['"', '`'], '', $sql), ForeignProfile::$sql));
    }

    #[Test]
    public function it_restricts_the_engine_on_an_attribute_that_the_perimeter_declares_the_same_way(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class, RedEverywhereExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame(['team' => ['red']], ForeignProfile::$builders[0]->whereIns);
    }

    #[Test]
    public function it_refuses_a_profile_that_claims_sql_restrictions_without_applying_them(): void
    {
        $this->useEngine(new ForeignEngine);
        SqlForeignEngineProfiles::$skipsRows = true;
        config(['rest.extensions' => [GuestlessExtension::class, SqlForeignEngineProfiles::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::unappliedRowRestriction(VoucherResource::class, RestrictingForeignProfile::class, ForeignEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_names_the_profile_that_skips_the_restrictions(): void
    {
        $this->assertSame(
            'The engine profile '.RestrictingForeignProfile::class.' of ['.ForeignEngine::class.'] implements AppliesRowRestrictions, but its restrictedPage() returned without applying the RowRestriction it received for a text search of '.VoucherResource::class.'. Apply it to the SQL query of the page, or do not implement AppliesRowRestrictions so that the extensions restrict the engine with RestrictsEngineQueries.',
            InvalidEngineQueryException::unappliedRowRestriction(VoucherResource::class, RestrictingForeignProfile::class, ForeignEngine::class)->getMessage(),
        );
    }
}
