<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class OwnedTotalsOptionsTest extends EngineTestCase
{
    #[Test]
    #[DataProvider('ownedOptions')]
    public function it_refuses_a_facet_option_on_a_resource_that_declares_totals(string $engine, string $option): void
    {
        $this->useEngine($engine === 'typesense' ? new FakeTypesenseEngine : new FacetingMeilisearchEngine([]));
        $this->engineQuery(VoucherResource::class)->options([$option => 'kept']);

        $this->expectExceptionObject(InvalidEngineQueryException::ownedOption($option));

        $this->engineQuery(TotalledVoucherResource::class)->options([$option => 'kept']);
    }

    /** @return array<string, array{string, string}> */
    public static function ownedOptions(): array
    {
        $typesense = ['facet_by', 'max_facet_values', 'facet_query', 'facet_strategy', 'facet_sample_percent', 'facet_sample_threshold', 'facet_return_parent', 'search_cutoff_ms', 'pinned_hits', 'hidden_hits'];

        return [
            ...array_combine(array_map(static fn (string $option): string => "typesense {$option}", $typesense), array_map(static fn (string $option): array => ['typesense', $option], $typesense)),
            'meilisearch facets' => ['meilisearch', 'facets'],
        ];
    }

    /** @param class-string<VoucherResource> $resource */
    private function engineQuery(string $resource): EngineQuery
    {
        config(['scout.typesense.model-settings.Lomkit\Rest\Tests\Fixtures\Ledger\Voucher.search-parameters.query_by' => 'title']);
        $text = $this->resource($resource)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        return $text->query(new TextQuery('report'), $this->target($resource));
    }
}
