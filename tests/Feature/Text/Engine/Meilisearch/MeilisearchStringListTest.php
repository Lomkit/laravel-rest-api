<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GroupInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchStringListTest extends MeilisearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;
        $this->indexes->configure($this->index, ['filterableAttributes' => ['number', 'price', 'active', 'code', 'team', 'title', '__soft_deleted', 'readers']]);
        ReaderVoucher::withTrashed()->get()->searchable();
        $this->settle();
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

        Route::prefix('api')->group(fn () => Rest::resource('reader-vouchers', ReaderVoucherController::class)->withSoftDeletes());
    }

    /**
     * @param  list<string>  $groups
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('readers')]
    public function it_matches_the_rows_that_share_a_value_with_the_list(array $groups, bool $excludes, array $ids): void
    {
        ReadersExtension::$excludes = $excludes;
        ReadersExtension::$groups = $groups;

        $response = $this->actingAs(new GenericUser(['id' => 'reader']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', count($ids));
        $this->assertEqualsCanonicalizing($ids, $response->json('data.*.id'));
    }

    /** @return array<string, array{list<string>, bool, list<int>}> */
    public static function readers(): array
    {
        return [
            'a reader alone on a list' => [['cid'], false, [4]],
            'a reader among others' => [['ann'], false, [3]],
            'a reader on two rows' => [['bob'], false, [1, 3]],
            'the rows without a reader, an empty list included' => [['bob'], true, [4, 5]],
            'the rows without another reader' => [['ann'], true, [1, 4, 5]],
            'two groups on rows that hold one of them each' => [['ann', 'cid'], false, [3, 4]],
            'two groups on a row that holds both and a row that holds one' => [['ann', 'bob'], false, [1, 3]],
            'the rows that hold none of two groups' => [['ann', 'cid'], true, [1, 5]],
            'a row that holds one of two groups beside another value left out' => [['bob', 'cid'], true, [5]],
        ];
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
}
