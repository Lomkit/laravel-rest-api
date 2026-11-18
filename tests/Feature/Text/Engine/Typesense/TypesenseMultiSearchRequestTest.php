<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingHttpClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
#[RequiresMethod(Client::class, 'getMultiSearch')]
class TypesenseMultiSearchRequestTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    private const array HITS = ['hits' => [['document' => ['id' => 'V-a', 'code' => 'V-a']]], 'found' => 1];

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
    public function it_sends_a_short_search_as_a_get_on_the_search_endpoint(): void
    {
        $client = $this->engineWith(self::HITS);
        ReadersExtension::$groups = ['ann', 'bob'];

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([['GET', '/collections/'.(new ReaderVoucher)->searchableAs().'/documents/search', http_build_query(self::searchParameters('readers:=[`ann`, `bob`]')), '']], $client->requests);
    }

    #[Test]
    public function it_posts_a_long_search_to_multi_search_with_its_collection(): void
    {
        $client = $this->engineWith(['results' => [self::HITS]]);
        $readers = ['ann', ...array_map(fn (int $index): string => sprintf('reader-%04d', $index), range(1, 299))];
        ReadersExtension::$groups = $readers;

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $filter = 'readers:=['.implode(', ', array_map(fn (string $reader): string => "`{$reader}`", $readers)).']';
        $search = [...self::searchParameters($filter), 'prefix' => true, 'exhaustive_search' => false, 'use_cache' => false, 'prioritize_exact_match' => true, 'enable_overrides' => true, 'collection' => (new ReaderVoucher)->searchableAs()];
        $this->assertSame([['POST', '/multi_search', '', (string) json_encode(['searches' => [$search]])]], $client->requests);
    }

    #[Test]
    public function it_throws_the_error_of_a_long_search_that_multi_search_answers_with_200(): void
    {
        $this->engineWith(['results' => [['code' => 400, 'error' => 'Could not find a filter field named `readers` in the schema.']]]);
        ReadersExtension::$groups = ['ann', ...array_map(fn (int $index): string => sprintf('reader-%04d', $index), range(1, 299))];
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(EngineSearchException::typesenseMultiSearch((new ReaderVoucher)->searchableAs(), ['code' => 400, 'error' => 'Could not find a filter field named `readers` in the schema.']));

        $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
    }

    /** @param array<mixed> $answer */
    private function engineWith(array $answer): RecordingHttpClient
    {
        $client = new RecordingHttpClient($answer);
        $this->useEngine(new TypesenseEngine(new Client([
            'api_key' => 'test-key',
            'nodes' => [['host' => 'typesense.test', 'port' => '8108', 'protocol' => 'http']],
            'num_retries' => 0,
            'client' => $client,
        ]), 1000));

        return $client;
    }

    /** @return array<string, mixed> */
    private static function searchParameters(string $filter): array
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
            'exhaustive_search' => 'false',
            'use_cache' => 'false',
            'cache_ttl' => 60,
            'prioritize_exact_match' => 'true',
            'enable_overrides' => 'true',
            'highlight_affix_num_tokens' => 4,
            'prefix' => 'true',
        ];
    }
}
