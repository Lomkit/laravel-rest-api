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
        config(['rest.extensions' => ['readers' => ReadersExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();
        ReadersExtension::$excludes = false;

        parent::tearDown();
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('reader-vouchers', ReaderVoucherController::class)->withSoftDeletes());
    }

    #[Test]
    #[DataProvider('filters')]
    public function it_sends_a_list_attribute_as_a_facet_filter(bool $excludes, string $filters): void
    {
        ReadersExtension::$excludes = $excludes;
        $this->hits(['V-a'], 1);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", ['query' => 'report', 'filters' => $filters, 'hitsPerPage' => 50, 'page' => 0]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }

    /** @return array<string, array{bool, string}> */
    public static function filters(): array
    {
        return [
            'the rows that share a reader' => [false, "__soft_deleted:'0' AND (readers:'ann')"],
            'the rows that share no reader' => [true, "__soft_deleted:'0' AND NOT readers:'ann'"],
        ];
    }
}
