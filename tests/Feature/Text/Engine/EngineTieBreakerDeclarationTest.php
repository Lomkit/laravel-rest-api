<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineTieBreakerDeclarationTest extends ScoutEngineDeclarationTestCase
{
    #[Test]
    public function it_states_the_tie_breaker_and_reserves_a_typesense_sort_for_it(): void
    {
        $this->useTypesense();

        $tied = $this->capabilities($this->open(['price', 'title'], ['price'])->servesLists()->tieBreaker('title'))->engine;
        $untied = $this->capabilities($this->open(['price', 'title'], ['price'])->servesLists())->engine;

        $this->assertSame([2, 'title', 3], [$tied?->listSortKeys, $tied?->tieBreaker, $tied?->sortKeys]);
        $this->assertSame([3, null], [$untied?->listSortKeys, $untied?->tieBreaker]);
        $this->assertSame('{"filters":[],"sorts":["price"],"max_sorts":3,"max_page_size":250,"window":null,"lists":true,"max_list_sorts":2,"tie_breaker":"title","groups":true}', json_encode([...$tied?->jsonSerialize() ?? [], 'filters' => []]));
    }

    #[Test]
    public function it_keeps_every_sort_for_the_client_on_an_engine_without_a_sort_to_spare(): void
    {
        $this->useEngine(new FakeTurbopufferEngine);
        $turbopuffer = $this->capabilities($this->open(['price'], ['price'])->servesLists()->tieBreaker('title'))->engine;
        config(['scout.driver' => 'collection']);
        $collection = $this->capabilities($this->open(['price'], ['price'])->servesLists()->tieBreaker('title'))->engine;

        $this->assertSame([1, 'title'], [$turbopuffer?->listSortKeys, $turbopuffer?->tieBreaker]);
        $this->assertSame([PHP_INT_MAX, 'title'], [$collection?->listSortKeys, $collection?->tieBreaker]);
    }

    #[Test]
    public function it_refuses_the_typesense_document_id_in_the_sorts(): void
    {
        $this->useTypesense();

        $this->expectExceptionObject(InvalidTextSearchException::reservedSortAttribute(VoucherResource::class, 'id', FakeTypesenseEngine::class));

        $this->capabilities($this->open(['id', 'price'], ['price', 'id']));
    }

    #[Test]
    public function it_refuses_the_typesense_document_id_as_the_tie_breaker(): void
    {
        $this->useTypesense();

        $this->expectExceptionObject(InvalidTextSearchException::reservedSortAttribute(VoucherResource::class, 'id', FakeTypesenseEngine::class));

        $this->capabilities($this->open(['price'], ['price'])->servesLists()->tieBreaker('id'));
    }

    #[Test]
    public function it_accepts_the_id_sorts_on_an_engine_that_sorts_them(): void
    {
        config(['scout.driver' => 'collection']);

        $engine = $this->capabilities($this->open(['id', 'price'], ['id'])->servesLists()->tieBreaker('id'))->engine;

        $this->assertSame([['id'], 'id'], [$engine?->sorts, $engine?->tieBreaker]);
    }

    #[Test]
    public function it_refuses_a_tie_breaker_on_an_engine_that_takes_no_sort_without_text(): void
    {
        $this->useEngine(new FakeAlgolia4Engine(new SettingsClient(fn (): array => [])));

        $this->expectExceptionObject(InvalidTextSearchException::unsortedTieBreaker(VoucherResource::class, 'title', FakeAlgolia4Engine::class));

        $this->capabilities($this->open(['price'], ['price'])->servesLists()->tieBreaker('title'));
    }

    #[Test]
    public function it_refuses_a_tie_breaker_on_a_resource_that_does_not_serve_its_lists(): void
    {
        config(['scout.driver' => 'collection']);

        $this->expectExceptionObject(InvalidTextSearchException::unlistedTieBreaker(VoucherResource::class, 'title'));

        $this->capabilities($this->open(['price'], ['price'])->tieBreaker('title'));
    }

    #[Test]
    public function it_refuses_a_tie_breaker_that_is_not_an_attribute_name(): void
    {
        config(['scout.driver' => 'collection']);

        $this->expectExceptionObject(InvalidTextSearchException::invalidAttributeName(VoucherResource::class, 'created at'));

        $this->capabilities($this->open(['price'])->servesLists()->tieBreaker('created at'));
    }

    private function useTypesense(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
    }
}
