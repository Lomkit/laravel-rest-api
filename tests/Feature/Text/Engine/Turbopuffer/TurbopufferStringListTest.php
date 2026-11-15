<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TeamReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('turbopuffer')]
class TurbopufferStringListTest extends TurbopufferTestCase
{
    private const array RANKED = ['rank_by' => ['title', 'BM25', 'report']];

    private const array COUNT = ['aggregate_by' => ['count' => ['Count']]];

    private const array UNTRASHED = ['__soft_deleted', 'Eq', 0];

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('scout.turbopuffer.model-settings.'.ReaderVoucher::class, ['searchable-attributes' => ['title']]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;
        config(['rest.extensions' => [ReadersExtension::class]]);
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

        Route::prefix('api')->group(function (): void {
            Rest::resource('reader-vouchers', ReaderVoucherController::class)->withSoftDeletes();
            Rest::resource('team-reader-vouchers', TeamReaderVoucherController::class)->withSoftDeletes();
        });
    }

    #[Test]
    public function it_sends_a_list_membership_as_contains_any_in_the_ranked_and_the_count_request(): void
    {
        $this->rows(['V-c', 'V-a'], 2);

        $response = $this->actingAs(new GenericUser(['id' => 'bob']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $filters = ['And', [['readers', 'ContainsAny', ['bob']], self::UNTRASHED]];
        $response->assertOk()->assertExactJson($this->page([1, 3], 2));
        $this->assertSame([
            [...self::RANKED, 'filters' => $filters, 'limit' => 50],
            [...self::COUNT, 'filters' => $filters],
        ], $this->queries());
    }

    #[Test]
    public function it_sends_a_list_exclusion_as_not_contains_any_in_the_ranked_and_the_count_request(): void
    {
        ReadersExtension::$excludes = true;
        ReadersExtension::$groups = ['ann', 'cid'];
        $this->rows(['V-e'], 1);

        $response = $this->actingAs(new GenericUser(['id' => 'bob']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $filters = ['And', [['readers', 'NotContainsAny', ['ann', 'cid']], self::UNTRASHED]];
        $response->assertOk()->assertExactJson($this->page([5], 1));
        $this->assertSame([
            [...self::RANKED, 'filters' => $filters, 'limit' => 50],
            [...self::COUNT, 'filters' => $filters],
        ], $this->queries());
    }

    #[Test]
    public function it_ands_a_list_with_the_perimeter_the_client_filters_and_an_instruction(): void
    {
        config(['rest.extensions' => [ReadersExtension::class, ReaderInstructionsExtension::class]]);
        ReadersExtension::$groups = ['ann', 'bob'];
        $this->rows(['V-c'], 1);

        $response = $this->actingAs(new GenericUser(['id' => 'ann']))->search('team-reader-vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]],
            'instructions' => [['name' => 'ri.bob']],
        ]);

        $filters = ['And', [['readers', 'ContainsAny', ['bob']], ['And', [self::UNTRASHED, ['team', 'Eq', 'red'], ['price', 'Lt', 40]]]]];
        $response->assertOk()->assertExactJson($this->page([1], 1));
        $this->assertSame([
            ['consistency' => ['level' => 'eventual'], ...self::RANKED, 'filters' => $filters, 'limit' => 50],
            [...self::COUNT, 'filters' => $filters, 'consistency' => ['level' => 'eventual']],
        ], $this->queries());
    }

    #[Test]
    public function it_keeps_sending_a_single_valued_membership_as_in_beside_a_list(): void
    {
        $this->rows(['V-c', 'V-a'], 2);

        $response = $this->actingAs(new GenericUser(['id' => 'bob']))->search('reader-vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 3]]],
        ]);

        $filters = ['And', [['readers', 'ContainsAny', ['bob']], ['And', [self::UNTRASHED, ['number', 'In', [1, 3]]]]]];
        $response->assertOk()->assertExactJson($this->page([1, 3], 2));
        $this->assertSame([
            [...self::RANKED, 'filters' => $filters, 'limit' => 50],
            [...self::COUNT, 'filters' => $filters],
        ], $this->queries());
    }

    #[Test]
    public function it_keeps_sending_a_single_valued_membership_and_exclusion_as_in_and_not_in(): void
    {
        config(['rest.extensions' => []]);
        $this->rows(['V-c'], 1);

        $response = $this->search('reader-vouchers', [
            'text' => ['value' => 'report'],
            'filters' => [
                ['field' => 'number', 'operator' => 'in', 'value' => [1, 3]],
                ['field' => 'price', 'operator' => 'not in', 'value' => [7.25]],
            ],
        ]);

        $filters = ['And', [self::UNTRASHED, ['number', 'In', [1, 3]], ['price', 'NotIn', [7.25]]]];
        $response->assertOk()->assertExactJson($this->page([1], 1));
        $this->assertSame([
            [...self::RANKED, 'filters' => $filters, 'limit' => 50],
            [...self::COUNT, 'filters' => $filters],
        ], $this->queries());
    }

    #[Test]
    public function it_tells_both_kinds_that_turbopuffer_matches_lists(): void
    {
        $this->rows([], 0);

        $this->actingAs(new GenericUser(['id' => 'ann']))->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $declared = EngineLog::$contexts[0] ?? null;
        $restricted = EngineLog::$contexts[1] ?? null;
        $this->assertInstanceOf(IndexAttributesContext::class, $declared);
        $this->assertInstanceOf(EngineRestrictionContext::class, $restricted);
        $this->assertSame([true, true], [$declared->matchesStringLists, $restricted->matchesStringLists]);
    }

    #[Test]
    public function it_reads_the_keys_of_an_action_with_the_list_in_every_request(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        $this->rows(['V-c', 'V-a'], 2);

        $response = $this->actingAs(new GenericUser(['id' => 'bob']))->postJson('/api/reader-vouchers/actions/retitle-vouchers', ['search' => ['text' => ['value' => 'report']]]);

        $filters = ['And', [['readers', 'ContainsAny', ['bob']], self::UNTRASHED]];
        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [1, 3]]]], $received->calls());
        $this->assertSame([
            [...self::RANKED, 'filters' => $filters, 'limit' => 2],
            [...self::COUNT, 'filters' => $filters],
            [...self::RANKED, 'filters' => $filters, 'limit' => 4],
            [...self::COUNT, 'filters' => $filters],
        ], $this->queries());
    }

    #[Test]
    public function it_sends_no_request_when_the_restriction_matches_nothing(): void
    {
        $this->rows(['V-c'], 1);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertSame([], $this->queries());
    }
}
