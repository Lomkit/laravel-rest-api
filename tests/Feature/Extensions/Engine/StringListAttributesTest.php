<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Exceptions\ConflictingListsException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AdaptiveReadersExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia3Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\RestrictingExtension;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class StringListAttributesTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;
        Route::prefix('api')->group(fn () => Rest::resource('reader-vouchers', ReaderVoucherController::class)->register());
        config([
            'rest.extensions' => ['readers' => ReadersExtension::class],
            'scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title',
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('listEngines')]
    public function it_accepts_a_string_list_on_engines_that_match_lists(string $engine): void
    {
        $this->useEngine($this->engine($engine));

        $grammar = $this->capabilities()->engine;

        $this->assertNotNull($grammar);
        $this->assertArrayNotHasKey('readers', $grammar->filters);
    }

    /** @return array<string, array{class-string}> */
    public static function listEngines(): array
    {
        return [
            'algolia 3' => [FakeAlgolia3Engine::class],
            'algolia 4' => [FakeAlgolia4Engine::class],
            'meilisearch' => [FakeMeilisearchEngine::class],
            'typesense' => [FakeTypesenseEngine::class],
        ];
    }

    #[Test]
    #[DataProvider('scalarEngines')]
    public function it_declares_a_string_list_on_every_engine(string $engine): void
    {
        $this->useEngine($this->engine($engine));

        $grammar = $this->capabilities()->engine;

        $this->assertNotNull($grammar);
        $this->assertArrayNotHasKey('readers', $grammar->filters);
    }

    #[Test]
    #[DataProvider('scalarEngines')]
    public function it_refuses_to_compare_a_string_list_on_engines_that_compare_single_values(string $engine): void
    {
        $this->useEngine($this->engine($engine));
        $query = $this->engineQuery();

        $refusals = [];

        foreach (['whereIn', 'whereNotIn'] as $method) {
            try {
                $query->{$method}('readers', ['ann']);
            } catch (InvalidEngineQueryException $exception) {
                $refusals[] = $exception->getMessage();
            }
        }

        $expected = InvalidEngineQueryException::unmatchedStringList(ReaderVoucherResource::class, 'readers', $engine)->getMessage();
        $this->assertSame([$expected, $expected], $refusals);
    }

    #[Test]
    public function it_searches_with_a_declared_list_that_is_not_compared(): void
    {
        config(['scout.driver' => 'collection']);

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([], 0));
    }

    /** @return array<string, array{class-string}> */
    public static function scalarEngines(): array
    {
        return [
            'collection' => [CollectionEngine::class],
            'database' => [DatabaseEngine::class],
            'turbopuffer' => [FakeTurbopufferEngine::class],
            'null' => [NullEngine::class],
        ];
    }

    #[Test]
    public function it_names_the_list_the_engine_and_where_lists_match(): void
    {
        $this->assertSame(
            'The engine query of '.ReaderVoucherResource::class.' compares the list attribute [readers], but the engine ['.CollectionEngine::class.'] compares single values only. Compare lists of strings on an engine whose profile implements Lomkit\\Rest\\Text\\Engine\\Profiles\\MatchesStringLists, and check $context->matchesStringLists before comparing the list.',
            InvalidEngineQueryException::unmatchedStringList(ReaderVoucherResource::class, 'readers', CollectionEngine::class)->getMessage(),
        );
    }

    #[Test]
    public function it_compares_a_string_list_with_in_and_not_in_only(): void
    {
        $this->useEngine(new FakeTypesenseEngine);

        $this->expectExceptionObject(InvalidEngineQueryException::unsupportedOperator('readers', '='));

        $this->engineQuery()->where('readers', '=', 'ann');
    }

    #[Test]
    public function it_refuses_a_list_value_that_is_not_a_string(): void
    {
        $this->useEngine(new FakeTypesenseEngine);

        $this->expectExceptionObject(InvalidEngineQueryException::invalidValue('readers', ['ann']));

        $this->engineQuery()->whereIn('readers', [['ann']]);
    }

    #[Test]
    public function it_refuses_two_lists_that_neither_holds_on_one_list_attribute(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        $query = $this->engineQuery()->whereIn('readers', ['ann', 'bob']);

        $this->expectExceptionObject(ConflictingListsException::memberships(ReaderVoucherResource::class, 'readers', null, null));

        $query->whereIn('readers', ['ann', 'cid']);
    }

    #[Test]
    public function it_names_the_two_extensions_whose_lists_neither_holds(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['rest.extensions' => ['readers' => ReadersExtension::class, 'groups' => ReaderGroupsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $this->withoutExceptionHandling();
        $exception = ConflictingListsException::memberships(ReaderVoucherResource::class, 'readers', new RestrictingExtension('groups', ReaderGroupsExtension::class), new RestrictingExtension('readers', ReadersExtension::class));

        $this->assertSame('The engine query of '.ReaderVoucherResource::class.' keeps the rows whose list attribute [readers] shares a value with each of two lists, set by the [groups] extension '.ReaderGroupsExtension::class.' and the [readers] extension '.ReadersExtension::class.', and neither list holds the other. The engine matches one list per attribute: restrict [readers] with one list, or with lists that hold one another.', $exception->getMessage());
        $this->expectExceptionObject($exception);

        $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_answers_422_to_an_instruction_list_that_conflicts_with_an_extension(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['rest.extensions' => ['readers' => ReadersExtension::class, 'ri' => ReaderInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $response = $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'ri.bob']]]);

        $message = 'The search.instructions.0 field restricts the list [readers] with values that neither hold nor are held by the list that the search already keeps.';
        $response->assertStatus(422)->assertExactJson(['message' => $message, 'errors' => ['search.instructions.0' => [$message]]]);
    }

    #[Test]
    public function it_names_the_perimeter_or_an_instruction_beside_an_extension(): void
    {
        $this->assertSame(
            'The engine query of '.ReaderVoucherResource::class.' keeps the rows whose list attribute [readers] shares a value with each of two lists, set by the [readers] extension '.ReadersExtension::class.' and ScoutEngine::perimeter() or an instruction, and neither list holds the other. The engine matches one list per attribute: restrict [readers] with one list, or with lists that hold one another.',
            ConflictingListsException::memberships(ReaderVoucherResource::class, 'readers', new RestrictingExtension('readers', ReadersExtension::class), null)->getMessage(),
        );
    }

    #[Test]
    public function it_sends_a_string_list_filter_to_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();
        ReadersExtension::$excludes = true;
        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame(['readers:=[`ann`]', 'readers:!=[`ann`]'], $engine->filters);
    }

    #[Test]
    public function it_sends_a_string_list_of_two_values_to_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        ReadersExtension::$groups = ['ann', 'cid'];

        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();
        ReadersExtension::$excludes = true;
        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame(['readers:=[`ann`, `cid`]', 'readers:!=[`ann`, `cid`]'], $engine->filters);
    }

    #[Test]
    #[DataProvider('listSupport')]
    public function it_tells_both_kinds_whether_the_engine_matches_lists(string $engine, bool $matches): void
    {
        $this->useEngine($this->engine($engine));
        $this->actingAs(new GenericUser(['id' => 'ann']));

        $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $declared = EngineLog::$contexts[0];
        $this->assertInstanceOf(IndexAttributesContext::class, $declared);
        $this->assertSame($matches, $declared->matchesStringLists);

        if ($matches) {
            $restricted = EngineLog::$contexts[1];
            $this->assertInstanceOf(EngineRestrictionContext::class, $restricted);
            $this->assertSame([true, false], [$restricted->matchesStringLists, $restricted->comparesColumns]);
        }
    }

    /** @return array<string, array{class-string, bool}> */
    public static function listSupport(): array
    {
        return [
            'typesense' => [FilterCapturingTypesenseEngine::class, true],
            'collection' => [CollectionEngine::class, false],
        ];
    }

    #[Test]
    public function it_serves_every_engine_with_one_extension(): void
    {
        config(['rest.extensions' => ['readers' => AdaptiveReadersExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $typesense = new FilterCapturingTypesenseEngine;

        config(['scout.driver' => 'collection']);
        $collection = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
        config(['scout.driver' => 'database']);
        $database = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);
        $this->useEngine($typesense);
        $engine = $this->search('reader-vouchers', ['text' => ['value' => 'report']]);

        $collection->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
        $database->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
        $engine->assertOk();
        $this->assertSame(['readers:=[`ann`]'], $typesense->filters);
    }

    private function engine(string $engine): object
    {
        return match ($engine) {
            FakeMeilisearchEngine::class, FakeAlgolia3Engine::class, FakeAlgolia4Engine::class => new $engine(new SettingsClient(fn (): array => [])),
            default => new $engine,
        };
    }

    private function capabilities(): TextCapabilities
    {
        $text = $this->resource(ReaderVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        return $text->capabilities($this->target(ReaderVoucherResource::class));
    }

    private function engineQuery(): EngineQuery
    {
        $text = $this->resource(ReaderVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        return $text->query(new TextQuery('report'), $this->target(ReaderVoucherResource::class));
    }
}
