<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Actions\Rows\KeyChunks;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Exceptions\EngineResultsChangedException;
use Lomkit\Rest\Exceptions\EngineWindowException;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Query\SqlSearch;
use Lomkit\Rest\Search\Plan\PlannedRows;
use Lomkit\Rest\Search\Run\PayloadSearchRequest;
use Lomkit\Rest\Tests\Concerns\CatchesLazyRefusals;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedAlgoliaEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PagedMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainProfile;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ShiftingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\WalkedEngineProfiles;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class LazyEngineWalkTest extends EngineSearchTestCase
{
    use CatchesLazyRefusals;
    use ResetsExtensionStubs;

    private const array REPORT = ['text' => ['value' => 'report']];

    private const int EXTRA = 1100;

    #[Test]
    #[DataProvider('unboundedEngines')]
    public function it_walks_an_engine_without_a_page_bound_in_pages_of_a_thousand(string $name): void
    {
        $this->seedExtraReports();
        $settings = fn (): array => ['maxTotalHits' => 5000, 'paginationLimitedTo' => 5000];
        $engine = $name === 'meilisearch' ? new PagedMeilisearchEngine(new SettingsClient($settings)) : new PagedAlgoliaEngine(new SettingsClient($settings));
        $this->useEngine($engine);

        $ids = Rest::search(UnsortedVoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy()
            ->pluck('id')
            ->all();

        $this->assertSame([[1000, 1], [1000, 2]], $engine->pages);
        $this->assertCount(4 + self::EXTRA, $ids);
        $this->assertCount(4 + self::EXTRA, array_unique($ids));
    }

    /** @return array<string, array{string}> */
    public static function unboundedEngines(): array
    {
        return [
            'Meilisearch' => ['meilisearch'],
            'Algolia' => ['algolia'],
        ];
    }

    #[Test]
    public function it_walks_a_provided_profile_without_a_page_bound_in_pages_of_a_thousand(): void
    {
        $rows = Rest::search(PlainEngineVoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy();

        $this->assertSame([[1000, 1]], PlainProfile::$pages);
        $this->assertSame([3], $rows->pluck('id')->all());
    }

    #[Test]
    public function it_refuses_when_lazy_is_called_a_total_that_changes_between_pages_under_a_max(): void
    {
        $this->useEngine(new ShiftingCollectionEngine(repeats: false, shrinks: true));
        config(['rest.extensions' => [WalkedEngineProfiles::class]]);

        $refusal = $this->lazyRefusal(fn () => Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(self::REPORT)
            ->lazy(max: 3));

        $this->assertInstanceOf(EngineResultsChangedException::class, $refusal);
        $this->assertSame(SearchRunException::engineResultsChanged(VoucherResource::class)->getMessage(), $refusal->getMessage());
    }

    /** @param list<int>|null $ids */
    #[Test]
    #[DataProvider('uncountedReaches')]
    public function it_bounds_an_engine_without_exact_total_by_its_reach(?int $max, ?array $ids): void
    {
        config(['scout.driver' => 'collection', 'rest.extensions' => [WalkedEngineProfiles::class]]);
        WalkedEngineProfiles::$exactTotal = false;
        WalkedEngineProfiles::$reachable = 3;

        $lazy = fn () => Rest::search(ListedVoucherResource::class)
            ->as(null)
            ->payload(['source' => 'engine'])
            ->lazy(max: $max);

        if ($ids !== null) {
            $this->assertSame($ids, $lazy()->pluck('id')->all());

            return;
        }

        $refusal = $this->lazyRefusal($lazy);

        $this->assertInstanceOf(EngineWindowException::class, $refusal);
        $this->assertSame(3, $refusal->window);
        $this->assertSame(SearchRunException::engineWindow(ListedVoucherResource::class, 3)->getMessage(), $refusal->getMessage());
    }

    /** @return array<string, array{?int, list<int>|null}> */
    public static function uncountedReaches(): array
    {
        return [
            'no max' => [null, null],
            'a max beyond the reach' => [4, null],
            'a max at the reach' => [3, [5, 4, 1]],
            'a max under the reach' => [2, [5, 4]],
        ];
    }

    #[Test]
    public function it_keeps_the_planned_rows_of_the_sql_search(): void
    {
        $request = PayloadSearchRequest::of($this->app, VoucherResource::class, [], ActingUser::of('web', null), false);
        $request->run();
        $resource = $request->context()->resource(VoucherResource::class);

        $rows = (new SqlSearch($resource, $resource->context()->text($resource), $request->payload()))->chunks(2, null);

        $this->assertInstanceOf(PlannedRows::class, $rows);
        $this->assertInstanceOf(KeyChunks::class, $rows->chunks);
        $this->assertSame([[5, 3], [2, 1]], array_map(fn ($chunk): array => $chunk->map(fn (Model $row): mixed => $row->getKey())->all(), iterator_to_array($rows->chunks->chunks(), false)));
    }

    private function seedExtraReports(): void
    {
        $rows = array_map(fn (int $id): array => ['id' => $id, 'code' => sprintf('X-%04d', $id), 'number' => $id, 'team' => 'red', 'title' => "Report extra {$id}", 'price' => 2.5, 'active' => true], range(7, 6 + self::EXTRA));

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('vouchers')->insert($chunk);
        }
    }
}
