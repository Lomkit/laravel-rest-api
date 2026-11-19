<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GroupInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch\MeilisearchStringListTest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\IndexedReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('typesense')]
class TypesenseStringListTest extends TypesenseTestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $settings = $app['config']->get('scout.typesense.model-settings.'.Voucher::class);
        $settings['collection-schema']['fields'][] = ['name' => 'readers', 'type' => 'string[]'];
        $app['config']->set('scout.typesense.model-settings.'.ReaderVoucher::class, $settings);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;
        $this->collections->drop($this->collection);
        ReaderVoucher::withTrashed()->get()->searchable();
        config(['rest.extensions' => [ReadersExtension::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;

        parent::tearDown();
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(function (): void {
            Rest::resource('reader-vouchers', ReaderVoucherController::class)->withSoftDeletes();
            Rest::resource('indexed-reader-vouchers', IndexedReaderVoucherController::class)->withSoftDeletes();
        });
    }

    /**
     * @param  list<string>  $groups
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProviderExternal(MeilisearchStringListTest::class, 'readers')]
    public function it_matches_the_rows_that_share_a_value_with_the_list(array $groups, bool $excludes, array $ids): void
    {
        ReadersExtension::$excludes = $excludes;
        ReadersExtension::$groups = $groups;

        $response = $this->actingAs(new GenericUser(['id' => 'reader']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', count($ids));
        $this->assertEqualsCanonicalizing($ids, $response->json('data.*.id'));
    }

    #[Test]
    public function it_matches_a_list_inside_an_extension_group(): void
    {
        ReadersExtension::$groups = ['ann', 'bob', 'cid'];
        config(['rest.extensions' => [ReadersExtension::class, ReaderGroupEngineRestriction::class]]);

        $response = $this->actingAs(new GenericUser(['id' => 'reader']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([1, 3], $response->json('data.*.id'));
    }

    #[Test]
    public function it_keeps_only_the_rows_that_hold_a_value_of_an_instruction_list_of_index_attributes(): void
    {
        config(['rest.extensions' => []]);

        $response = $this->search('indexed-reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'bob']]]);

        $response->assertOk()->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([1, 3], $response->json('data.*.id'));
    }

    #[Test]
    public function it_keeps_only_the_rows_that_share_a_value_with_each_of_two_lists(): void
    {
        ReadersExtension::$groups = ['ann'];
        config(['rest.extensions' => [ReadersExtension::class, ReaderInstructionsExtension::class]]);

        $both = $this->actingAs(new GenericUser(['id' => 'reader']))->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'ri.bob']]]);
        $three = $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'ri.bob-and-cid']]]);

        $both->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.*.id', [3]);
        $three->assertOk()->assertJsonPath('total', 0);
    }

    /** @param list<int> $ids */
    #[Test]
    #[DataProvider('restrictedGroups')]
    public function it_never_returns_a_row_that_a_restriction_excludes_through_an_instruction_group(array $groups, array $filters, array $ids): void
    {
        ReadersExtension::$groups = $groups;
        config(['rest.extensions' => [ReadersExtension::class, GroupInstructionsExtension::class]]);

        $response = $this->actingAs(new GenericUser(['id' => 'reader']))->search('reader-vouchers', ['text' => ['value' => 'report'], 'filters' => $filters, 'instructions' => [['name' => 'gi.reader-group']]]);

        $response->assertOk()->assertJsonPath('total', count($ids));
        $this->assertEqualsCanonicalizing($ids, $response->json('data.*.id'));
    }

    /** @return array<string, array{list<string>, list<array<string, mixed>>, list<int>}> */
    public static function restrictedGroups(): array
    {
        return [
            'a restriction that keeps both rows of the group' => [['ann', 'bob'], [], [1, 3]],
            'a restriction that keeps one row of the group' => [['ann'], [], [3]],
            'a client or on the row that the restriction excludes' => [['ann'], [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 1, 'type' => 'or']], [3]],
        ];
    }

    #[Test]
    public function it_runs_a_search_whose_filter_passes_the_query_string_limit_through_multi_search(): void
    {
        ReadersExtension::$groups = ['ann', ...array_map(fn (int $index): string => sprintf('reader-%04d', $index), range(1, 299))];

        $response = $this->actingAs(new GenericUser(['id' => 'reader']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.*.id', [3]);
    }
}
