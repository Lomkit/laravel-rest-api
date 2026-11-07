<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CheckedVoucherController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\LockedVoucherPolicy;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('app.debug', false)]
class EngineRowChecksTest extends EngineSearchTestCase
{
    private const array REPORT = ['search' => ['text' => ['value' => 'report']]];

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));
        Gate::policy(Voucher::class, LockedVoucherPolicy::class);
        Route::prefix('api')->group(fn () => Rest::resource('checked-vouchers', CheckedVoucherController::class)->register());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_checks_the_rows_that_the_engine_matches_before_the_action_runs(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson('/api/checked-vouchers/actions/lock-vouchers', self::REPORT);

        $response->assertForbidden()->assertExactJson(['message' => 'This action is unauthorized.']);
        $this->assertSame([], $this->received->calls());
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_runs_on_the_allowed_rows_that_the_engine_matches(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson('/api/checked-vouchers/actions/lock-allowed-vouchers', self::REPORT);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [4]]], ['handle', ['ids' => [1, 3]]]], $this->received->calls());
    }
}
