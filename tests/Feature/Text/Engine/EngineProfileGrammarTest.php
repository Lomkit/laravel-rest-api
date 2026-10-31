<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnboundedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnboundedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineProfileGrammarTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('unsorted-vouchers', UnsortedVoucherController::class)->register();
            Rest::resource('unbounded-vouchers', UnboundedVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('searchesBeyondTheEngine')]
    public function it_refuses_pages_and_sorts_beyond_what_the_engine_returns(string $engine, string $resource, array $search, array $errors): void
    {
        $this->useEngine(new $engine);

        $response = $this->search($resource, ['text' => ['value' => 'report'], ...$search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{class-string, string, array<string, mixed>, array<string, list<string>>}> */
    public static function searchesBeyondTheEngine(): array
    {
        return [
            'a page larger than Typesense returns' => [FakeTypesenseEngine::class, 'unsorted-vouchers', ['limit' => 300], [
                'search.limit' => ['The search.limit field must not be greater than 250.'],
            ]],
            'more sorts than Typesense runs' => [FakeTypesenseEngine::class, 'vouchers', ['sorts' => [
                ['field' => 'price'], ['field' => 'number'], ['field' => 'price', 'direction' => 'desc'], ['field' => 'number', 'direction' => 'desc'],
            ]], [
                'search.sorts' => ['The search.sorts field must not have more than 3 items.'],
            ]],
            'a page beyond the Turbopuffer window' => [FakeTurbopufferEngine::class, 'unsorted-vouchers', ['limit' => 2, 'page' => 5001], [
                'search.page' => ['The search.page field must not be greater than 5000.'],
            ]],
            'a default page beyond the Turbopuffer window' => [FakeTurbopufferEngine::class, 'unsorted-vouchers', ['page' => 201], [
                'search.page' => ['The search.page field must not be greater than 200.'],
            ]],
        ];
    }

    #[Test]
    public function it_keeps_the_pages_of_the_database_grammar_without_terms(): void
    {
        $this->useEngine(new FakeTurbopufferEngine);

        $response = $this->search('unsorted-vouchers', ['limit' => 2, 'page' => 5001]);

        $response->assertOk()->assertExactJson($this->page([], 4, 2, 5001));
    }

    #[Test]
    public function it_runs_the_engine_order_alone_on_an_engine_that_does_not_sort(): void
    {
        $engine = new RecordingCollectionEngine;
        $this->useEngine($engine);

        $response = $this->search('unsorted-vouchers', ['text' => ['value' => 'report'], 'sorts' => [['field' => '@relevance']]]);

        $response->assertOk()->assertJsonPath('data.*.id', [5, 1, 3]);
        $this->assertSame([], $engine->searches[0]->orders);
    }

    #[Test]
    public function it_refuses_the_engine_order_without_terms(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => ' '], 'sorts' => [['field' => '@relevance']]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.'],
        ]);
    }

    #[Test]
    public function it_refuses_every_search_of_a_resource_whose_engine_declares_no_index_perimeter(): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::missingPerimeter(UnboundedVoucherResource::class));

        $this->search('unbounded-vouchers', []);
    }
}
