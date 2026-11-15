<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Algolia;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('algolia')]
class AlgoliaStringListTest extends AlgoliaTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;
        config(['rest.extensions' => [ReadersExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
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

    /** @param list<string>|null $groups */
    #[Test]
    #[DataProvider('filters')]
    public function it_sends_a_list_attribute_as_a_facet_filter(?array $groups, bool $excludes, string $filters): void
    {
        ReadersExtension::$excludes = $excludes;
        ReadersExtension::$groups = $groups;
        $this->hits(['V-a'], 1);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", ['query' => 'report', 'filters' => $filters, 'hitsPerPage' => 50, 'page' => 0]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }

    /** @return array<string, array{list<string>|null, bool, string}> */
    public static function filters(): array
    {
        return [
            'the rows that share a reader' => [null, false, "__soft_deleted:'0' AND (readers:'ann')"],
            'the rows that share no reader' => [null, true, "__soft_deleted:'0' AND NOT readers:'ann'"],
            'the rows that share one of two groups' => [['ann', 'cid'], false, "__soft_deleted:'0' AND (readers:'ann' OR readers:'cid')"],
            'the rows that share none of two groups' => [['ann', 'cid'], true, "__soft_deleted:'0' AND NOT readers:'ann' AND NOT readers:'cid'"],
        ];
    }
}
