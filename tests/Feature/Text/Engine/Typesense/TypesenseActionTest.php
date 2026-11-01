<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TitledVoucherController;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
class TypesenseActionTest extends TypesenseTestCase
{
    private const array BY_PRICE = [['field' => 'price', 'direction' => 'asc']];

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 'blue']]));
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('titled-vouchers', TitledVoucherController::class));
    }

    #[Test]
    public function it_reads_the_keys_of_an_action_in_pages_the_engine_returns(): void
    {
        $engine = new RecordingTypesenseEngine(new Client(config('scout.typesense.client-settings')), 1000, config('scout.typesense'));
        $this->useEngine($engine);

        $response = $this->postJson('/api/titled-vouchers/actions/deactivate-vouchers-in-large-chunks', ['search' => ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4]]);
        $this->assertSame([['handle', ['ids' => [1, 3, 4, 5]]]], $this->received->calls());
        $this->assertSame([[250, 1]], $engine->pages);
    }

    #[Test]
    public function it_reads_every_key_before_the_action_changes_the_indexed_rows(): void
    {
        $response = $this->postJson('/api/team-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report'], 'trashed' => 'with', 'sorts' => self::BY_PRICE]]);
        $report = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'trashed' => 'with']);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5]]);
        $this->assertSame([['handle', ['ids' => [1, 6]]], ['handle', ['ids' => [3, 4]]], ['handle', ['ids' => [5]]]], $this->received->calls());
        $report->assertOk()->assertExactJson($this->page([], 0));
    }
}
