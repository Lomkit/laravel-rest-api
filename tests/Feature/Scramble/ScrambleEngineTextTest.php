<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Feature\Scramble\Stubs\CappedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ScrambleEngineTextTest extends ScrambleTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Ledger];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, ScoutServiceProvider::class, RestServiceProvider::class];
    }

    #[Test]
    public function it_documents_the_words_searched_in_the_index_of_the_resource(): void
    {
        $search = $this->search('/vouchers/search');

        $this->assertSame([
            'type' => 'object',
            'description' => 'Words to search in the search index of the resource; the index decides which attributes they match',
            'properties' => [
                'value' => ['type' => ['string', 'null'], 'maxLength' => 255],
                'mode' => ['type' => 'string', 'enum' => ['words']],
            ],
            'additionalProperties' => false,
        ], $search['text']);
    }

    #[Test]
    public function it_documents_the_database_filters_and_sorts_of_a_resource_searched_on_an_engine(): void
    {
        $search = $this->search('/vouchers/search');

        $this->assertSame(['id', 'code', 'number', 'team', 'title', 'price', 'active', 'lines.id', 'lines.voucher_id', 'lines.label'], array_keys((array) $this->schemas()['VoucherFieldFilter']['discriminator']['mapping']));
        $this->assertSame(['$ref' => '#/components/schemas/VoucherFilter'], $search['filters']['items']);
        $this->assertSame(['id', 'code', 'number', 'team', 'title', 'price', 'active', 'lines_count', 'lines_min_label', 'lines_max_label', 'lines_exists', '@relevance'], $search['sorts']['items']['properties']['field']['enum']);
    }

    /** @param array<string, mixed> $trashed */
    #[Test]
    #[DataProvider('trashed')]
    public function it_documents_whether_the_trashed_models_combine_with_a_text_search(bool $softDelete, array $trashed): void
    {
        config(['scout.soft_delete' => $softDelete]);

        $this->assertSame($trashed, $this->search('/vouchers/search')['trashed']);
    }

    /** @return array<string, array{bool, array<string, mixed>}> */
    public static function trashed(): array
    {
        return [
            'an index that keeps the trashed models' => [true, ['type' => 'string', 'enum' => ['with', 'only']]],
            'an index that drops the trashed models' => [false, [
                'type' => 'string',
                'description' => 'Cannot be combined with a text search: the search index of the resource does not keep the trashed models',
                'enum' => ['with', 'only'],
            ]],
        ];
    }

    #[Test]
    public function it_documents_a_capped_total_that_a_text_search_on_the_engine_leaves_out(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('capped-vouchers', CappedVoucherController::class));

        $page = app(Generator::class)()['components']['schemas']['CappedVoucherPaginated'];

        $this->assertSame(
            '{"type":"boolean","description":"True when more rows match than the total counts: total is then a lower bound. On a page of the search engine, present only when the engine caps its total, such as Meilisearch at the maxTotalHits of its index"}',
            json_encode($page['properties']['total_capped']),
        );
        $this->assertSame(['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'meta'], $page['required']);
    }

    #[Test]
    public function it_documents_the_engine_grammar_of_a_resource_that_serves_its_lists_through_the_engine(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('listed-vouchers', ListedVoucherController::class));
        $schemas = app(Generator::class)()['components']['schemas'];
        $search = $schemas['ListedVoucherSearch']['properties'];

        $this->assertFalse($search['scopes']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineFilter'], $search['filters']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineSort'], $search['sorts']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineInstruction'], $search['instructions']['items']);
        $this->assertSame($search['filters'], $schemas['ListedVoucherActionSearch']['properties']['filters']);
        $this->assertFalse($schemas['ListedVoucherActionSearch']['properties']['scopes']);
        $this->assertSame(['number', 'price', 'active', 'code'], array_keys((array) $schemas['ListedVoucherEngineFilter']['discriminator']['mapping']));
        $this->assertSame(
            '{"type":"object","properties":{"field":{"type":"string","const":"code"},"operator":{"type":"string","enum":["in","not in"]},"value":{"type":"array","items":{"type":"string","maxLength":1000},"minItems":1,"maxItems":100},"type":{"type":"string","enum":["and"]}},"required":["field","operator","value"],"additionalProperties":false}',
            json_encode($schemas['ListedVoucherEngineFilterCode']['oneOf'][1]),
        );
        $this->assertSame(['price', 'number', 'code', '@relevance'], $schemas['ListedVoucherEngineSort']['properties']['field']['enum']);
        $this->assertSame(['priced-up-to', 'numbered-from'], array_keys((array) $schemas['ListedVoucherEngineInstruction']['discriminator']['mapping']));
        $this->assertSame(['type' => 'integer', 'description' => 'Present on a page of the search engine: the rows that the engine returned for the page and that the database read left out (a deleted row, a global scope, a row restriction). They are missing from data and still counted in total. Above 0, the index is stale, or the restrictions of the engine and of the database disagree'], $schemas['ListedVoucherPaginated']['properties']['dropped']);
        $this->assertSame(['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'meta'], $schemas['ListedVoucherPaginated']['required']);
    }
}
