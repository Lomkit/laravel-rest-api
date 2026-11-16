<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineListRequestTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('listed-vouchers', ListedVoucherController::class)->register());
    }

    #[Test]
    public function it_sends_the_match_all_query_of_typesense_with_the_filters_the_key_sort_and_the_page(): void
    {
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);

        $response = $this->search('listed-vouchers', ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'limit' => 2, 'page' => 2]);

        $response->assertOk();
        $this->assertSame(
            ['q' => '*', 'filter_by' => 'price:<40', 'per_page' => 2, 'page' => 2, 'sort_by' => 'code:asc'],
            array_intersect_key($engine->parameters[0], array_flip(['q', 'filter_by', 'per_page', 'page', 'sort_by'])),
        );
    }

    #[Test]
    public function it_sends_the_requested_sort_to_typesense_in_place_of_the_key_sort(): void
    {
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);

        $this->search('listed-vouchers', ['sorts' => [['field' => 'price', 'direction' => 'desc'], ['field' => 'number']]])->assertOk();

        $this->assertSame(['*', 'price:desc,number:asc'], [$engine->parameters[0]['q'], $engine->parameters[0]['sort_by']]);
    }

    #[Test]
    public function it_sends_the_empty_query_of_meilisearch_with_the_filters_and_the_key_sort(): void
    {
        $engine = new CapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);

        $response = $this->search('listed-vouchers', ['filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 3]]]]);

        $response->assertOk()->assertExactJson([...$this->page([3, 1], 2), 'total_capped' => false]);
        $this->assertSame([['q' => '', 'filter' => 'number IN [1, 3]', 'sort' => ['code:asc']]], $engine->requests);
    }

    /** @param array<string, mixed> $expected */
    #[Test]
    #[DataProvider('caps')]
    public function it_marks_a_meilisearch_total_capped_at_the_max_total_hits_of_the_index(mixed $settings, array $expected): void
    {
        $this->useEngine(new CapturingMeilisearchEngine(new SettingsClient(fn (): mixed => $settings)));

        $response = $this->search('listed-vouchers', ['limit' => 2]);

        $response->assertOk()->assertExactJson([...$this->page([3, 2], 5, 2), ...$expected]);
    }

    /** @return array<string, array{mixed, array<string, mixed>}> */
    public static function caps(): array
    {
        return [
            'a total at the cap' => [['maxTotalHits' => 5], ['total_capped' => true]],
            'a total under the cap' => [['maxTotalHits' => 6], ['total_capped' => false]],
            'an index that does not report its cap' => [null, []],
        ];
    }
}
