<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuestlessExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\JoiningRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedEverywhereExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TitledRowsExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class DatabaseEngineRestrictionTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'database']);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_restricts_the_rows_and_the_total_of_a_search_on_a_sql_driver(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [RedRowsExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
        $this->assertSame(['VoucherResource'], RestrictionLog::$seen);
    }

    #[Test]
    public function it_keeps_the_client_filters_on_the_collection_driver(): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [RedRowsExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [
            ['field' => 'number', 'operator' => '>', 'value' => 1],
            ['field' => 'code', 'operator' => 'in', 'value' => ['V-a', 'V-c', 'V-d']],
        ]]);

        $response->assertOk()->assertExactJson($this->page([1], 1));
    }

    #[Test]
    public function it_keeps_the_client_exclusions_on_the_collection_driver(): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [RedRowsExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [
            ['field' => 'code', 'operator' => 'not in', 'value' => ['V-a', 'V-d']],
        ]]);

        $response->assertOk()->assertExactJson($this->page([5, 1], 2));
    }

    #[Test]
    public function it_returns_an_empty_page_on_the_null_driver(): void
    {
        config(['scout.driver' => 'null', 'rest.extensions' => [RedRowsExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
    }

    #[Test]
    public function it_counts_the_restricted_total_on_a_later_page(): void
    {
        config(['rest.extensions' => [RedRowsExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 2, 'page' => 2]);

        $response->assertOk()->assertExactJson($this->page([3], 3, 2, 2));
    }

    #[Test]
    public function it_puts_each_extension_in_its_own_group(): void
    {
        config(['rest.extensions' => [RedRowsExtension::class, TitledRowsExtension::class]]);
        DB::enableQueryLog();

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 3], 2));
        $this->assertStringContainsString('and (vouchers.team = ?) and (vouchers.title != ? or vouchers.number = ?)', $this->loggedQuery(0));
    }

    #[Test]
    public function it_does_not_apply_the_perimeter_query_of_the_resource(): void
    {
        config(['rest.extensions' => [TitledRowsExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 3], 3));
    }

    #[Test]
    public function it_refuses_a_join_in_a_restriction_on_the_database_engine(): void
    {
        config(['rest.extensions' => [JoiningRowsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::restrictionClause('joining', JoiningRowsExtension::class, VoucherResource::class, QueryPart::Join));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_restricts_the_targets_of_an_action_on_a_sql_driver(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [RedRowsExtension::class]]);

        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame([1, 3, 5], Voucher::query()->where('active', false)->orderBy('id')->pluck('id')->all());
    }

    #[Test]
    public function it_hides_every_row_from_a_guest_with_a_false_condition(): void
    {
        config(['rest.extensions' => [GuestlessExtension::class]]);

        $guest = $this->search('vouchers', ['text' => ['value' => 'report']]);
        $user = $this->actingAs(new GenericUser(['id' => 1]))->search('vouchers', ['text' => ['value' => 'report']]);

        $guest->assertOk()->assertExactJson($this->page([], 0));
        $user->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
    }

    #[Test]
    public function it_gives_the_restriction_the_gate_user_of_rest_search_as(): void
    {
        config(['rest.extensions' => [HiddenRowsExtension::class]]);
        $user = new GenericUser(['id' => 7]);

        $page = Rest::search(VoucherResource::class)->as($user)->payload(['text' => ['value' => 'report']])->page();

        $this->assertSame(4, $page->total());
        $this->assertSame([$user], RestrictionLog::$users);
    }

    #[Test]
    public function it_applies_the_engine_restriction_and_the_sql_restriction_together(): void
    {
        config(['rest.extensions' => [RedEverywhereExtension::class]]);
        DB::enableQueryLog();

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
        $this->assertStringContainsString('and team in (?) and (vouchers.team = ?)', $this->loggedQuery(0));
    }

    #[Test]
    public function it_runs_each_restriction_on_the_engine_page_and_on_the_rows_read_again(): void
    {
        config(['rest.extensions' => [HiddenRowsExtension::class]]);

        $response = $this->postJson('/api/vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);
        $this->assertSame(array_fill(0, 4, 'VoucherResource guest'), RestrictionLog::$seen);
    }

    private function loggedQuery(int $index): string
    {
        return str_replace(['"', '`'], '', DB::getQueryLog()[$index]['query']);
    }
}
