<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSearchTotalsRefusalTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->register();
        });
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('engineSearches')]
    public function it_prohibits_totals_on_an_engine_search_of_a_resource_that_declares_none(string $driver, string $resource, array $search): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search($resource, [...$search, 'totals' => ['groups' => [['field' => 'active']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited.']]);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function engineSearches(): array
    {
        return [
            'a text search on the collection engine' => ['collection', 'vouchers', ['text' => ['value' => 'report']]],
            'a text search on the database engine' => ['database', 'vouchers', ['text' => ['value' => 'report']]],
            'an engine list' => ['collection', 'listed-vouchers', ['source' => 'engine']],
        ];
    }
}
