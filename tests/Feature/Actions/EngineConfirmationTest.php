<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ConfirmedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
class EngineConfirmationTest extends EngineSearchTestCase
{
    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-08 10:00:00');
        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Rest::resource('confirmed-vouchers', ConfirmedTeamVoucherController::class)->register();
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_counts_the_rows_that_the_engine_matches_before_the_confirmation(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $body = ['search' => ['text' => ['value' => 'report']]];

        $asked = $this->postJson('/confirmed-vouchers/actions/confirmed-deactivate-vouchers', $body);

        $asked->assertStatus(428)->assertJsonPath('message', 'Deactivate 4 vouchers?')->assertJsonPath('confirmation.data', ['rows' => 4]);
        $this->assertSame([], $this->received->calls());

        $response = $this->postJson('/confirmed-vouchers/actions/confirmed-deactivate-vouchers', [...$body, 'confirmation' => $asked->json('confirmation.token')]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [5, 4, 1, 3]]]], $this->received->calls());
    }
}
