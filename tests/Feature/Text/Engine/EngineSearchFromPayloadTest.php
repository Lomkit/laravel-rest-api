<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\InputEditingVoucherController;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSearchFromPayloadTest extends EngineSearchTestCase
{
    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Route::prefix('api')->group(fn () => Rest::resource('input-editing-vouchers', InputEditingVoucherController::class)->register());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_runs_the_validated_engine_search_when_before_search_edits_the_input(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('input-editing-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
        $this->assertSame([], $this->received->calls());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_acts_on_the_validated_engine_search_when_before_operate_edits_the_input(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson('/api/input-editing-vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [5, 4, 1, 3]]]], $this->received->calls());
    }
}
