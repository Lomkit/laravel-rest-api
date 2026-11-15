<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->collections->drop($this->collection);
        ReaderVoucher::withTrashed()->get()->searchable();
        config(['rest.extensions' => ['readers' => ReadersExtension::class]]);
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

    /** @param list<int> $ids */
    #[Test]
    #[DataProvider('readers')]
    public function it_matches_the_rows_that_share_a_value_with_the_list(string $reader, bool $excludes, array $ids): void
    {
        ReadersExtension::$excludes = $excludes;

        $response = $this->actingAs(new GenericUser(['id' => $reader]))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', count($ids));
        $this->assertEqualsCanonicalizing($ids, $response->json('data.*.id'));
    }

    /** @return array<string, array{string, bool, list<int>}> */
    public static function readers(): array
    {
        return [
            'a reader alone on a list' => ['cid', false, [4]],
            'a reader among others' => ['ann', false, [3]],
            'a reader on two rows' => ['bob', false, [1, 3]],
            'the rows without a reader, an empty list included' => ['bob', true, [4, 5]],
            'the rows without another reader' => ['ann', true, [1, 4, 5]],
        ];
    }
}
