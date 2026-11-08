<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class LazyEngineSearchTest extends EngineSearchTestCase
{
    #[Test]
    public function it_refuses_to_read_a_text_search_that_runs_on_an_engine(): void
    {
        config(['scout.driver' => 'collection']);

        $this->expectExceptionObject(SearchRunException::engineWindow(VoucherResource::class, null));
        $this->expectExceptionMessage('The text search of the resource ['.VoucherResource::class.'] runs on a search engine, which returns its results page by page.');

        Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(['text' => ['value' => 'report']])
            ->lazy();
    }

    #[Test]
    public function it_names_the_result_window_of_the_engine(): void
    {
        $this->useEngine(new FakeTurbopufferEngine);

        $this->expectExceptionObject(SearchRunException::engineWindow(UnsortedVoucherResource::class, 10000));
        $this->expectExceptionMessage('which returns at most its first 10000 results.');

        Rest::search(UnsortedVoucherResource::class)
            ->as(null)
            ->payload(['text' => ['value' => 'report']])
            ->lazy();
    }

    #[Test]
    public function it_reads_the_rows_of_a_search_without_text_from_the_database(): void
    {
        config(['scout.driver' => 'collection']);
        $data = $this->search('vouchers', ['sorts' => [['field' => 'number']]])->assertOk()->json('data');

        $rows = Rest::search(VoucherResource::class)
            ->as(null)
            ->payload(['sorts' => [['field' => 'number']]])
            ->lazy(chunk: 2);

        $this->assertSame([3, 2, 1, 5], $rows->pluck('id')->all());
        $this->assertSame(json_encode($data), json_encode($rows->all()));
    }
}
