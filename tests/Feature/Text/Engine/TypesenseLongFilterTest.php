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
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
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
        $this->assertSame([['search', self::parameters(self::filter(['ann', 'bob']))]], $engine->requests);
    }

    #[Test]
    public function it_keeps_a_search_of_exactly_the_threshold_on_the_search_endpoint_and_moves_one_character_more(): void
    {
        $engine = new MultiSearchTypesenseEngine;
        $this->useEngine($engine);

        foreach ([3500, 3501] as $length) {
            ReadersExtension::$groups = self::readersOfLength($length);
            $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();
        }

        $this->assertSame(['search', 'multi_search'], array_column($engine->requests, 0));
        $this->assertSame(3500, self::queryLength($engine->requests[0][1]));
        $this->assertSame(3501, self::queryLength(self::parameters(self::filter(self::readersOfLength(3501)))));
    }

    #[Test]
    public function it_sends_a_long_search_through_multi_search_with_its_collection(): void
    {
        $engine = new MultiSearchTypesenseEngine;
        $this->useEngine($engine);
        ReadersExtension::$groups = self::readers(300);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([['multi_search', ['searches' => [[...self::parameters(self::filter(self::readers(300))), 'collection' => (new ReaderVoucher)->searchableAs()]]]]], $engine->requests);
    }

    #[Test]
    public function it_pages_a_long_search_and_reads_its_total_from_multi_search(): void
    {
        $engine = new MultiSearchTypesenseEngine(['hits' => [['document' => ['id' => 'V-a', 'code' => 'V-a']]], 'found' => 11]);
        $this->useEngine($engine);
        ReadersExtension::$groups = self::readers(300);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'limit' => 10, 'page' => 2]);

        $response->assertOk()->assertExactJson($this->page([3], 11, 10, 2));
        $this->assertSame([['multi_search', ['searches' => [[...self::parameters(self::filter(self::readers(300)), 10, 2), 'collection' => (new ReaderVoucher)->searchableAs()]]]]], $engine->requests);
    }

    #[Test]
    public function it_sends_the_same_parameters_to_multi_search_as_to_the_search_endpoint_without_the_null_ones(): void
    {
        $engine = new MultiSearchTypesenseEngine;
        $this->useEngine($engine);

        $this->engineQuery()->whereIn('readers', self::readers(300))->options(['exclude_fields' => null, 'drop_tokens_threshold' => 1])->page(50, 1);

        $this->assertSame([['multi_search', ['searches' => [[...self::parameters(self::filter(self::readers(300)), options: ['drop_tokens_threshold' => 1]), 'collection' => (new ReaderVoucher)->searchableAs()]]]]], $engine->requests);
    }

    #[Test]
    public function it_leaves_a_vector_search_to_scout(): void
    {
        $engine = new MultiSearchTypesenseEngine;
        $this->useEngine($engine);

        $this->engineQuery()->whereIn('readers', self::readers(300))->options(['vector_query' => 'embedding:([], k: 10)'])->page(50, 1);

        $this->assertSame([['search', self::parameters(self::filter(self::readers(300)), options: ['vector_query' => 'embedding:([], k: 10)'])]], $engine->requests);
    }

    /** @param array<mixed> $answer */
    #[Test]
    #[DataProvider('refusedAnswers')]
    public function it_throws_when_multi_search_answers_no_result(array $answer, string $message, int $code): void
    {
        $this->useEngine(new MultiSearchTypesenseEngine(answer: $answer));
        ReadersExtension::$groups = self::readers(300);
        $this->withoutExceptionHandling();

        try {
            $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
            $this->fail('The answer of multi_search was not refused.');
        } catch (EngineSearchException $refusal) {
            $this->assertSame([str_replace('{collection}', (new ReaderVoucher)->searchableAs(), $message), $code], [$refusal->getMessage(), $refusal->getCode()]);
        }
    }

    /** @return array<string, array{array<mixed>, string, int}> */
    public static function refusedAnswers(): array
    {
        return [
            'an answer without results' => [['message' => 'Not Found'], 'Typesense gave an unexpected answer to the search of the collection [{collection}] that the package sent through /multi_search: the answer holds no search result. Check the version of the Typesense server and of typesense/typesense-php.', 0],
            'an empty list of results' => [['results' => []], 'Typesense gave an unexpected answer to the search of the collection [{collection}] that the package sent through /multi_search: the answer holds no search result. Check the version of the Typesense server and of typesense/typesense-php.', 0],
        ];
    }

    private function engineQuery(): EngineQuery
    {
        $text = $this->resource(ReaderVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        return $text->query(new TextQuery('report'), $this->target(ReaderVoucherResource::class));
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
        $padding = $length - self::queryLength(self::parameters(self::filter([...$base, 'r'])));

        return [...$base, 'r'.str_repeat('a', $padding)];
    }

    /** @param list<string> $readers */
    private static function filter(array $readers): string
    {
        return 'readers:=['.implode(', ', array_map(fn (string $reader): string => "`{$reader}`", $readers)).']';
    }

    /** @param array<mixed> $parameters */
    private static function queryLength(array $parameters): int
    {
        return strlen(http_build_query(array_map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, $parameters)));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function parameters(string $filter, int $perPage = 50, int $page = 1, array $options = []): array
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
            'exhaustive_search' => false,
            'use_cache' => false,
            'cache_ttl' => 60,
            'prioritize_exact_match' => true,
            'enable_overrides' => true,
            'highlight_affix_num_tokens' => 4,
            'prefix' => true,
            ...$options,
            'filter_curated_hits' => true,
        ];
    }
}
