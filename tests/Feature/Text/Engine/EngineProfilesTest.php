<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use ArrayObject;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia3Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\Profiles\AlgoliaProfile;
use Lomkit\Rest\Text\Engine\Profiles\CollectionProfile;
use Lomkit\Rest\Text\Engine\Profiles\DatabaseProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\MeilisearchProfile;
use Lomkit\Rest\Text\Engine\Profiles\NullProfile;
use Lomkit\Rest\Text\Engine\Profiles\TurbopufferProfile;
use Lomkit\Rest\Text\Engine\Profiles\TypesenseProfile;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[Group('scout')]
class EngineProfilesTest extends EngineTestCase
{
    /**
     * @param  class-string<EngineProfile>  $profile
     * @param  list<string>  $owned
     */
    #[Test]
    #[DataProvider('profiles')]
    public function it_states_what_each_scout_engine_does(string $engine, string $profile, int $sortKeys, array $owned, bool $exactTotal, ?int $maxPageSize, ?int $window): void
    {
        $resolved = EngineProfiles::for($this->engine($engine), VoucherResource::class);

        $this->assertInstanceOf($profile, $resolved);
        $this->assertSame(
            [$sortKeys, $owned, $exactTotal, $maxPageSize, $window],
            [$resolved->sortKeys(), $resolved->ownedOptions(), $resolved->exactTotal(), $resolved->maxPageSize(), $resolved->window()],
        );
    }

    /** @return array<string, array{class-string, class-string<EngineProfile>, int, list<string>, bool, ?int, ?int}> */
    public static function profiles(): array
    {
        return [
            'collection' => [CollectionEngine::class, CollectionProfile::class, PHP_INT_MAX, [], true, null, null],
            'database' => [DatabaseEngine::class, DatabaseProfile::class, PHP_INT_MAX, [], true, null, null],
            'meilisearch' => [FakeMeilisearchEngine::class, MeilisearchProfile::class, PHP_INT_MAX, ['filter', 'sort', 'page', 'hitsPerPage', 'limit', 'offset', 'q', 'attributesToRetrieve'], true, null, null],
            'typesense' => [FakeTypesenseEngine::class, TypesenseProfile::class, 3, ['q', 'filter_by', 'sort_by', 'per_page', 'page', 'group_by'], true, 250, null],
            'algolia 3' => [FakeAlgolia3Engine::class, AlgoliaProfile::class, 0, ['query', 'filters', 'hitsPerPage', 'page', 'offset', 'length'], true, null, null],
            'algolia 4' => [FakeAlgolia4Engine::class, AlgoliaProfile::class, 0, ['query', 'filters', 'hitsPerPage', 'page', 'offset', 'length'], true, null, null],
            'turbopuffer' => [FakeTurbopufferEngine::class, TurbopufferProfile::class, 0, ['rank_by', 'filters', 'top_k', 'include_attributes'], false, null, 10000],
            'null' => [NullEngine::class, NullProfile::class, PHP_INT_MAX, [], true, null, null],
        ];
    }

    #[Test]
    #[DataProvider('unboundedEngines')]
    public function it_reaches_every_hit_of_an_engine_without_pagination_limit(string $engine): void
    {
        $this->useEngine($this->engine($engine));
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);

        $this->assertSame(PHP_INT_MAX, $this->engineQuery()->reachable());
    }

    /** @return array<string, array{class-string}> */
    public static function unboundedEngines(): array
    {
        return [
            'collection' => [CollectionEngine::class],
            'database' => [DatabaseEngine::class],
            'typesense' => [FakeTypesenseEngine::class],
            'turbopuffer' => [FakeTurbopufferEngine::class],
            'null' => [NullEngine::class],
        ];
    }

    #[Test]
    #[DataProvider('settingsEngines')]
    public function it_reads_the_pagination_limit_of_the_index_only_when_asked(string $engine, string $key): void
    {
        $client = new SettingsClient(fn (): array => [$key => 800]);
        $this->useEngine(new $engine($client));

        $query = $this->engineQuery();

        $this->assertSame([], $client->indexes);
        $this->assertSame(800, $query->reachable());
        $this->assertSame(['vouchers'], $client->indexes);
    }

    /** @return array<string, array{class-string, string}> */
    public static function settingsEngines(): array
    {
        return [
            'meilisearch' => [FakeMeilisearchEngine::class, 'maxTotalHits'],
            'algolia 3' => [FakeAlgolia3Engine::class, 'paginationLimitedTo'],
            'algolia 4' => [FakeAlgolia4Engine::class, 'paginationLimitedTo'],
        ];
    }

    #[Test]
    public function it_reads_the_pagination_limit_from_settings_returned_as_an_object(): void
    {
        $this->useEngine(new FakeAlgolia4Engine(new SettingsClient(fn (): ArrayObject => new ArrayObject(['paginationLimitedTo' => 500]))));

        $this->assertSame(500, $this->engineQuery()->reachable());
    }

    #[Test]
    #[DataProvider('unreadableSettings')]
    public function it_knows_no_limit_when_the_index_settings_cannot_be_read(string $engine, mixed $settings): void
    {
        $this->useEngine(new $engine(new SettingsClient(fn (): mixed => $settings instanceof RuntimeException ? throw $settings : $settings)));

        $this->assertNull($this->engineQuery()->reachable());
    }

    /** @return array<string, array{class-string, mixed}> */
    public static function unreadableSettings(): array
    {
        return [
            'meilisearch refusing the read' => [FakeMeilisearchEngine::class, new RuntimeException('The key cannot read the settings.')],
            'meilisearch without limit' => [FakeMeilisearchEngine::class, []],
            'meilisearch with a limit that is not a number' => [FakeMeilisearchEngine::class, ['maxTotalHits' => 'many']],
            'algolia refusing the read' => [FakeAlgolia4Engine::class, new RuntimeException('The key cannot read the settings.')],
            'algolia without limit' => [FakeAlgolia4Engine::class, ['hitsPerPage' => 20]],
            'algolia with a negative limit' => [FakeAlgolia3Engine::class, ['paginationLimitedTo' => -1]],
        ];
    }

    #[Test]
    public function it_refuses_a_page_larger_than_the_engine_returns(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);

        $this->expectExceptionObject(InvalidEngineQueryException::pageTooLarge(251, 250));

        $this->engineQuery()->page(251, 1);
    }

    #[Test]
    public function it_refuses_a_page_beyond_the_engine_window(): void
    {
        $this->useEngine(new FakeTurbopufferEngine);

        $this->expectExceptionObject(InvalidEngineQueryException::beyondWindow(10000));

        $this->engineQuery()->page(100, 101);
    }

    /** @param class-string $engine */
    private function engine(string $engine): object
    {
        return match ($engine) {
            FakeMeilisearchEngine::class, FakeAlgolia3Engine::class, FakeAlgolia4Engine::class => new $engine(new SettingsClient(fn (): array => [])),
            default => new $engine,
        };
    }

    private function engineQuery(): EngineQuery
    {
        return ScoutEngine::make()
            ->filterable(['price'])
            ->withoutPerimeter()
            ->query(new TextQuery('report'), $this->target(VoucherResource::class), $this->context()->request);
    }
}
