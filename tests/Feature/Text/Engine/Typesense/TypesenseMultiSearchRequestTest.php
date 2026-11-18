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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame([['GET', '/collections/'.(new ReaderVoucher)->searchableAs().'/documents/search', http_build_query(self::searchParameters(self::filter(['ann', 'bob']))), '']], $client->requests);
    }

    #[Test]
    public function it_sends_a_query_string_of_exactly_3500_characters_as_a_get_and_one_more_to_multi_search(): void
    {
        $get = $this->engineWith(self::HITS);
        ReadersExtension::$groups = self::readersOfLength(3500);
        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();
        $post = $this->engineWith(['results' => [self::HITS]]);
        ReadersExtension::$groups = self::readersOfLength(3501);
        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame(['GET', 3500], [$get->requests[0][0], strlen($get->requests[0][2])]);
        $this->assertSame(['POST', '/multi_search', ''], array_slice($post->requests[0], 0, 3));
    }

    #[Test]
    public function it_posts_a_long_search_to_multi_search_with_its_collection_page_and_total(): void
    {
        $client = $this->engineWith(['results' => [['hits' => self::HITS['hits'], 'found' => 11]]]);
        ReadersExtension::$groups = self::readers(300);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'limit' => 10, 'page' => 2]);

        $response->assertOk()->assertExactJson($this->page([3], 11, 10, 2));
        $search = [...self::searchParameters(self::filter(self::readers(300)), 10, 2), 'prefix' => true, 'exhaustive_search' => false, 'use_cache' => false, 'prioritize_exact_match' => true, 'enable_overrides' => true, 'filter_curated_hits' => true, 'collection' => (new ReaderVoucher)->searchableAs()];
        $this->assertSame([['POST', '/multi_search', '', (string) json_encode(['searches' => [$search]])]], $client->requests);
    }

    /** @param array<mixed> $result */
    #[Test]
    #[DataProvider('typesenseErrors')]
    public function it_throws_the_error_of_a_long_search_with_the_typesense_exception_of_its_code(array $result, string $message, int $code, string $previous): void
    {
        $this->engineWith(['results' => [$result]]);
        ReadersExtension::$groups = self::readers(300);
        $this->withoutExceptionHandling();

        try {
            $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
            $this->fail('The error inside the multi_search result was not thrown.');
        } catch (EngineSearchException $refusal) {
            $expected = 'Typesense refused the search of the collection ['.(new ReaderVoucher)->searchableAs().'] that the package sent through /multi_search, since its query string passes the length that a GET search accepts: '.$message;
            $this->assertSame([$expected, $code, $previous], [$refusal->getMessage(), $refusal->getCode(), $refusal->getPrevious()::class]);
        }
    }

    /** @return array<string, array{array<mixed>, string, int, string}> */
    public static function typesenseErrors(): array
    {
        $schema = 'Check the filter and the collection schema.';

        return [
            'a malformed request' => [['code' => 400, 'error' => 'Bad filter.'], "Bad filter. (code 400). {$schema}", 400, 'Typesense\Exceptions\RequestMalformed'],
            'an unauthorized key' => [['code' => 401, 'error' => 'Forbidden.'], 'Forbidden. (code 401). Give the API key the documents:search action on the collection.', 401, 'Typesense\Exceptions\RequestUnauthorized'],
            'a missing collection' => [['code' => 404, 'error' => 'Collection not found'], "Collection not found (code 404). {$schema}", 404, 'Typesense\Exceptions\ObjectNotFound'],
            'a conflict' => [['code' => 409, 'error' => 'Conflict.'], "Conflict. (code 409). {$schema}", 409, 'Typesense\Exceptions\ObjectAlreadyExists'],
            'an unprocessable search' => [['code' => 422, 'error' => 'Unprocessable.'], "Unprocessable. (code 422). {$schema}", 422, 'Typesense\Exceptions\ObjectUnprocessable'],
            'an unavailable service' => [['code' => 503, 'error' => 'Unavailable.'], "Unavailable. (code 503). {$schema}", 503, 'Typesense\Exceptions\ServiceUnavailable'],
            'any other code' => [['code' => 500, 'error' => 'Boom.'], "Boom. (code 500). {$schema}", 500, 'Typesense\Exceptions\TypesenseClientError'],
            'an error without a code' => [['error' => 'Boom.'], "Boom. (without a code). {$schema}", 0, 'Typesense\Exceptions\TypesenseClientError'],
        ];
    }

    #[Test]
    public function it_refuses_a_multi_search_answer_without_results(): void
    {
        $this->engineWith(['message' => 'Not Found']);
        ReadersExtension::$groups = self::readers(300);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(EngineSearchException::unexpectedMultiSearch((new ReaderVoucher)->searchableAs()));

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

    /** @return list<string> */
    private static function readers(int $count): array
    {
        return ['ann', ...array_map(fn (int $index): string => sprintf('reader-%04d', $index), range(1, $count - 1))];
    }

    /** @return list<string> */
    private static function readersOfLength(int $length): array
    {
        $base = self::readers(100);
        $padding = $length - strlen(http_build_query(self::searchParameters(self::filter([...$base, 'r']))));

        return [...$base, 'r'.str_repeat('a', $padding)];
    }

    /** @param list<string> $readers */
    private static function filter(array $readers): string
    {
        return 'readers:=['.implode(', ', array_map(fn (string $reader): string => "`{$reader}`", $readers)).']';
    }

    /** @return array<string, mixed> */
    private static function searchParameters(string $filter, int $perPage = 50, int $page = 1): array
    {
        return [
            'q' => 'report',
            'query_by' => 'title',
            'filter_by' => $filter,
            'per_page' => $perPage,
            'page' => $page,
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
            'filter_curated_hits' => 'true',
        ];
    }
}
