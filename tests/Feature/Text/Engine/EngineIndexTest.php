<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineIndexTest extends EngineQueryTestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function it_queries_an_index_built_with_the_arguments_of_a_text_search_written_for_3_5(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $page = (new EngineQuery($this->index(), new TextQuery('report')))->whereIn('number', [1, 3])->page(10, 1);

        $this->assertEquals(new EnginePage(['V-c', 'V-a'], 2), $page);
    }

    #[Test]
    public function it_names_the_model_of_an_index_built_without_a_resource(): void
    {
        config(['scout.driver' => 'collection']);
        $index = $this->index();

        $this->assertNull($index->resource);
        $this->assertSame(Voucher::class, $index->searchedClass());
        $this->assertSame(VoucherResource::class, new EngineIndex($index->model, $index->profile, $index->attributes, VoucherResource::class)->searchedClass());
    }

    private function index(): EngineIndex
    {
        $target = $this->target(VoucherResource::class);
        $model = ScoutModel::tryFrom($target->model);
        $this->assertNotNull($model);
        $profile = EngineProfiles::forTarget($target);

        return new EngineIndex($model, $profile, IndexAttributes::of($target, $profile, ['number'], [], []));
    }
}
