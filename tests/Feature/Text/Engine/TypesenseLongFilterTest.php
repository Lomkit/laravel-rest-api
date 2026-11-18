<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\MultiSearchTypesenseEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Text\Engine\Profiles\TypesenseLongSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TypesenseLongFilterTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('reader-vouchers', ReaderVoucherController::class)->register());
        config([
            'rest.extensions' => [ReadersExtension::class],
            'scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title',
        ]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
    }

    #[Test]
    public function it_keeps_a_short_search_on_the_search_endpoint_unchanged(): void
    {
        $engine = new MultiSearchTypesenseEngine;
        $this->useEngine($engine);
        ReadersExtension::$groups = ['ann', 'bob'];

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([['search', self::parameters('readers:=[`ann`, `bob`]')]], $engine->requests);
    }

    #[Test]
    public function it_sends_a_long_search_through_multi_search_with_its_collection(): void
    {
        $engine = new MultiSearchTypesenseEngine;
        $this->useEngine($engine);
        ReadersExtension::$groups = self::readers(300);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $filter = 'readers:=['.implode(', ', array_map(fn (string $reader): string => "`{$reader}`", self::readers(300))).']';
        $this->assertSame([['multi_search', ['searches' => [[...self::parameters($filter), 'collection' => (new ReaderVoucher)->searchableAs()]]]]], $engine->requests);
    }

    #[Test]
    public function it_throws_the_error_that_multi_search_answers_inside_its_result(): void
    {
        $this->useEngine(new MultiSearchTypesenseEngine(['code' => 404, 'error' => 'Collection not found']));
        ReadersExtension::$groups = self::readers(300);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(EngineSearchException::typesenseMultiSearch((new ReaderVoucher)->searchableAs(), ['code' => 404, 'error' => 'Collection not found']));

        $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_names_the_collection_the_error_and_the_fix(): void
    {
        $this->assertSame(
            'Typesense refused the search of the collection [vouchers] that the package sent through /multi_search, since its query string passes the length that a GET search accepts: Collection not found (code 404). Check the filter and the collection schema, and give the API key the documents:search action on the collection.',
            EngineSearchException::typesenseMultiSearch('vouchers', ['code' => 404, 'error' => 'Collection not found'])->getMessage(),
        );
        $this->assertSame(404, EngineSearchException::typesenseMultiSearch('vouchers', ['code' => 404, 'error' => 'Collection not found'])->getCode());
        $this->assertStringContainsString(': no search result (code 500).', EngineSearchException::typesenseMultiSearch('vouchers', null)->getMessage());
    }

    /** @param array<string, mixed> $parameters */
    #[Test]
    #[DataProvider('queryStrings')]
    public function it_measures_the_query_string_that_the_search_endpoint_receives(array $parameters, int $length): void
    {
        $this->assertSame($length, TypesenseLongSearch::queryString($parameters));
        $this->assertSame(3500, TypesenseLongSearch::MAX_QUERY_STRING);
    }

    /** @return array<string, array{array<string, mixed>, int}> */
    public static function queryStrings(): array
    {
        return [
            'booleans as words' => [['prefix' => true, 'use_cache' => false], 27],
            'encoded characters' => [['filter_by' => 'readers:=[`a b`]'], 38],
            'numbers' => [['per_page' => 50, 'page' => 1], 18],
        ];
    }

    /** @return list<string> */
    private static function readers(int $count): array
    {
        return ['ann', ...array_map(fn (int $index): string => sprintf('reader-%04d', $index), range(1, $count - 1))];
    }

    /** @return array<string, mixed> */
    private static function parameters(string $filter): array
    {
        return [
            'q' => 'report',
            'query_by' => 'title',
            'filter_by' => $filter,
            'per_page' => 50,
            'page' => 1,
            'highlight_start_tag' => '<mark>',
            'highlight_end_tag' => '</mark>',
            'snippet_threshold' => 30,
            'exhaustive_search' => false,
            'use_cache' => false,
            'cache_ttl' => 60,
            'prioritize_exact_match' => true,
            'enable_overrides' => true,
            'highlight_affix_num_tokens' => 4,
            'prefix' => true,
        ];
    }
}
