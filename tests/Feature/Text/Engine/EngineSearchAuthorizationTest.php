<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Exceptions\UnplannedCheckException;
use Lomkit\Rest\Query\EngineSearch;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherLine;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSearchAuthorizationTest extends EngineSearchTestCase
{
    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
    }

    #[Test]
    public function it_checks_view_any_once_for_an_engine_search(): void
    {
        $checks = [];
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$checks): void {
            $checks[] = [$event->ability, $event->arguments];
        });

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'includes' => [['relation' => 'lines']]]);

        $response->assertOk()->assertJsonPath('data.*.id', [5, 1, 3]);
        $this->assertSame([['viewAny', [Voucher::class]], ['viewAny', [VoucherLine::class]]], $checks);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searchesDeniedByViewAny')]
    public function it_checks_view_any_before_the_engine_runs(array $search): void
    {
        Gate::before(fn (?Authenticatable $user): bool => false);
        DB::enableQueryLog();

        $response = $this->search('vouchers', $search);

        $response->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
        $this->assertSame([], $this->engine->searches);
        $this->assertSame([], DB::getQueryLog());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function searchesDeniedByViewAny(): array
    {
        return [
            'a text without hits' => [['text' => ['value' => 'invoice']]],
            'a page past the last one' => [['text' => ['value' => 'report'], 'page' => 999]],
            'a blank text' => [['text' => ['value' => '']]],
        ];
    }

    #[Test]
    public function it_throws_when_an_engine_search_runs_without_planning_the_listing(): void
    {
        $context = $this->context(['search' => ['text' => ['value' => 'report']]]);
        $resource = $context->resource(VoucherResource::class);
        $search = new EngineSearch($resource, $context->text($resource));

        try {
            $search->paginate($context->request);
            $this->fail('The engine search ran without planning the listing.');
        } catch (UnplannedCheckException $exception) {
            $this->assertSame('The [viewAny] check on ['.VoucherResource::class.'] was not planned before validation. Record it in the reach of the request.', $exception->getMessage());
        }

        $this->assertSame([], $this->engine->searches);
    }
}
