<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PricedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PricedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineStrictColumnsTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('priced-vouchers', PricedVoucherController::class)->register();
        PricedVoucherResource::$prices = [];
        Model::preventAccessingMissingAttributes(false);
    }

    #[Test]
    public function it_refuses_a_searched_event_that_reads_a_column_that_an_engine_page_did_not_load(): void
    {
        $response = $this->postJson('/priced-vouchers/search', ['search' => ['text' => ['value' => 'report'], 'selects' => [['field' => 'title']]]]);

        $response->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $response->exception);
        $this->assertSame(
            'The ['.PricedVoucherResource::class.'] search did not load the column [price] that a policy or a field of ['.Voucher::class.'] reads. Add it to alwaysSelect() of the resource or to requires() of the field, or set rest.search.select_columns to false.',
            $response->exception->getMessage(),
        );
        $this->assertSame([], PricedVoucherResource::$prices);
        $this->assertFalse(Model::preventsAccessingMissingAttributes());
    }

    #[Test]
    public function it_runs_the_searched_event_of_an_engine_page_on_the_loaded_columns(): void
    {
        $response = $this->postJson('/priced-vouchers/search', ['search' => ['text' => ['value' => 'report'], 'selects' => [['field' => 'title'], ['field' => 'price']]]]);

        $response->assertOk()->assertJsonPath('data.*.title', ['Report inactive', 'Report blue', 'Report alpha', 'Report beta']);
        $this->assertSame([40.0, 12.0, 1.5, 7.25], PricedVoucherResource::$prices);
    }
}
