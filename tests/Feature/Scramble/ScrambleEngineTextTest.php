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
            '{"type":"boolean","description":"True when more rows match than the total counts: total is then a lower bound. On a page of the search engine, present only when the engine caps its total, such as Turbopuffer at 10,000"}',
            json_encode($page['properties']['total_capped']),
        );
        $this->assertSame(['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'meta'], $page['required']);
    }

    #[Test]
    public function it_documents_the_engine_branch_of_a_resource_that_serves_engine_lists_on_request(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('listed-vouchers', ListedVoucherController::class));
        $schemas = app(Generator::class)()['components']['schemas'];
        $search = $schemas['ListedVoucherEngineSearch']['properties'];

        $this->assertFalse($search['scopes']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineFilter'], $search['filters']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineSort'], $search['sorts']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineInstruction'], $search['instructions']['items']);
        $this->assertSame($search['filters'], $schemas['ListedVoucherEngineActionSearch']['properties']['filters']);
        $this->assertFalse($schemas['ListedVoucherEngineActionSearch']['properties']['scopes']);
        $this->assertSame([['$ref' => '#/components/schemas/ListedVoucherEngineFieldFilter'], ['$ref' => '#/components/schemas/ListedVoucherEngineFilterGroup']], $schemas['ListedVoucherEngineFilter']['oneOf']);
        $this->assertSame(
            '{"type":"object","description":"Filters that count as one condition. Groups nest at most 1 deep.","properties":{"nested":{"type":"array","items":{"$ref":"#/components/schemas/ListedVoucherEngineFilter"},"maxItems":100},"type":{"type":"string","enum":["and","or"]}},"required":["nested"],"additionalProperties":false,"title":"ListedVoucherEngineFilterGroup"}',
            json_encode($schemas['ListedVoucherEngineFilterGroup'], JSON_UNESCAPED_SLASHES),
        );
        $this->assertSame(['number', 'price', 'active', 'code'], array_keys((array) $schemas['ListedVoucherEngineFieldFilter']['discriminator']['mapping']));
        $this->assertSame(
            '{"type":"object","properties":{"field":{"type":"string","const":"code"},"operator":{"type":"string","enum":["in","not in"]},"value":{"type":"array","items":{"type":"string","maxLength":1000},"minItems":1,"maxItems":100},"type":{"type":"string","enum":["and","or"]}},"required":["field","operator","value"],"additionalProperties":false}',
            json_encode($schemas['ListedVoucherEngineFilterCode']['oneOf'][1]),
        );
        $this->assertSame(['price', 'number', 'code', '@relevance'], $schemas['ListedVoucherEngineSort']['properties']['field']['enum']);
        $this->assertSame(['priced-up-to', 'numbered-from'], array_keys((array) $schemas['ListedVoucherEngineInstruction']['discriminator']['mapping']));
        $this->assertSame(['type' => 'integer', 'description' => 'Present on a page of the search engine: the rows that the engine returned for the page and that the database read by key left out (a row deleted since indexing, a global scope, trashed). They are missing from data and still counted in total. 0 in the normal case; above 0, the index holds rows that the database no longer serves'], $schemas['ListedVoucherPaginated']['properties']['dropped']);
        $this->assertSame(['current_page', 'data', 'from', 'last_page', 'per_page', 'to', 'total', 'meta'], $schemas['ListedVoucherPaginated']['required']);
    }

    #[Test]
    #[DataProvider('searchShapes')]
    public function it_documents_the_sql_search_and_the_engine_search_of_a_resource_that_serves_engine_lists_on_request(string $suffix): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('listed-vouchers', ListedVoucherController::class));
        $schemas = app(Generator::class)()['components']['schemas'];
        $database = $schemas["ListedVoucherDatabase{$suffix}"];
        $engine = $schemas["ListedVoucherEngine{$suffix}"];

        $this->assertSame(['oneOf' => [['$ref' => "#/components/schemas/ListedVoucherDatabase{$suffix}"], ['$ref' => "#/components/schemas/ListedVoucherEngine{$suffix}"]], 'title' => "ListedVoucher{$suffix}"], $schemas["ListedVoucher{$suffix}"]);
        $this->assertSame('{"type":"string","description":"The default: the search runs as the SQL search of the resource, with its whole grammar. Not the Scout database driver","enum":["database"]}', json_encode($database['properties']['source']));
        $this->assertArrayNotHasKey('required', $database);
        $this->assertSame('{"type":"object","description":"A blank text only, which keeps the SQL search: a text with a letter or digit runs on the search engine, so send it with source engine","properties":{"value":{"type":["string","null"],"pattern":"^\\\\s*$","maxLength":255},"mode":{"type":"string","enum":["words"]}},"additionalProperties":false}', json_encode($database['properties']['text']));
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherFilter'], $database['properties']['filters']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherScope'], $database['properties']['scopes']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherInstruction'], $database['properties']['instructions']['items']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherSort'], $database['properties']['sorts']['items']);
        $this->assertSame(['id', 'code', 'number', 'team', 'title', 'price', 'active', 'lines_count', 'lines_min_label', 'lines_max_label', 'lines_exists'], $schemas['ListedVoucherSort']['properties']['field']['enum']);
        $this->assertSame(['type' => 'string', 'enum' => ['with', 'only']], $database['properties']['trashed']);
        $this->assertSame('{"type":"string","description":"The search runs on the search engine of the resource, with or without a text","enum":["engine"]}', json_encode($engine['properties']['source']));
        $this->assertSame(['source'], $engine['required']);
        $this->assertSame('Words to search in the search index of the resource; the index decides which attributes they match', $engine['properties']['text']['description']);
        $this->assertSame(['$ref' => '#/components/schemas/ListedVoucherEngineFilter'], $engine['properties']['filters']['items']);
        $this->assertFalse($engine['properties']['scopes']);
        $this->assertSame(['type' => 'string', 'description' => 'Refused on the search engine, whose index does not keep the trashed models', 'enum' => ['with', 'only']], $engine['properties']['trashed']);
    }

    /** @return array<string, array{string}> */
    public static function searchShapes(): array
    {
        return [
            'the search' => ['Search'],
            'the action search' => ['ActionSearch'],
        ];
    }

    #[Test]
    public function it_documents_one_search_without_source_for_a_resource_that_does_not_serve_engine_lists(): void
    {
        $schemas = app(Generator::class)()['components']['schemas'];

        $this->assertArrayNotHasKey('oneOf', $schemas['VoucherSearch']);
        $this->assertArrayNotHasKey('source', $schemas['VoucherSearch']['properties']);
        $this->assertArrayNotHasKey('VoucherEngineSearch', $schemas);
        $this->assertArrayNotHasKey('VoucherDatabaseSearch', $schemas);
    }
}
