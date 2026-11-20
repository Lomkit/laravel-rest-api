<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SqlForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\ScoutEngineDeclarationTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\CentsVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\FacetingVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineTotalsDeclarationTest extends ScoutEngineDeclarationTestCase
{
    use ResetsExtensionStubs;

    #[Test]
    public function it_states_the_totals_that_the_engine_computes(): void
    {
        $capabilities = $this->capabilities($this->open(['number', 'price', 'active', 'code'])->groupable(['active', 'number'])->measurable(['price']));

        $this->assertSame('{"groups":["active","number"],"measures":{"price":["sum","avg","min","max"]}}', json_encode($capabilities->engine?->totals));
        $this->assertSame('{"groups":["active"],"measures":{}}', json_encode($capabilities->engine?->without(['number', 'price'], [])->totals));
    }

    #[Test]
    public function it_keeps_the_totals_through_every_other_declaration(): void
    {
        $engine = ScoutEngine::make()
            ->groupable(['active'])
            ->measurable(['price'])
            ->filterable(['price', 'active'])
            ->sortable(['price'])
            ->indexAttributes([Text::make('team')])
            ->perimeter(static fn (EngineQuery $query): EngineQuery => $query)
            ->servesLists()
            ->tieBreaker('code');

        $this->assertSame('{"groups":["active"],"measures":{"price":["sum","avg","min","max"]}}', json_encode($this->capabilities($engine)->engine?->totals));
    }

    /** @param Closure(ScoutEngine): ScoutEngine $declare */
    #[Test]
    #[DataProvider('refusals')]
    public function it_refuses_a_total_on_an_attribute_that_the_engine_cannot_compute(Closure $declare, InvalidTextSearchException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->capabilities($declare($this->open(['number', 'price', 'active', 'code'])->indexAttributes([Text::make('team')])));
    }

    /** @return array<string, array{Closure(ScoutEngine): ScoutEngine, InvalidTextSearchException}> */
    public static function refusals(): array
    {
        return [
            'a group outside filterable()' => [static fn (ScoutEngine $engine): ScoutEngine => $engine->groupable(['title']), InvalidTextSearchException::ungroupableAttribute(VoucherResource::class, 'title')],
            'a group on an index attribute' => [static fn (ScoutEngine $engine): ScoutEngine => $engine->groupable(['team']), InvalidTextSearchException::ungroupableAttribute(VoucherResource::class, 'team')],
            'a measure outside filterable()' => [static fn (ScoutEngine $engine): ScoutEngine => $engine->measurable(['team']), InvalidTextSearchException::unmeasurableAttribute(VoucherResource::class, 'team')],
            'a grouped number' => [static fn (ScoutEngine $engine): ScoutEngine => $engine->groupable(['price']), InvalidTextSearchException::ungroupableAttributeType(VoucherResource::class, 'price', 'number', 'Laravel\Scout\Engines\CollectionEngine')],
            'a measured text' => [static fn (ScoutEngine $engine): ScoutEngine => $engine->measurable(['code']), InvalidTextSearchException::unmeasurableAttributeType(VoucherResource::class, 'code', 'text', 'Laravel\Scout\Engines\CollectionEngine')],
            'a repeated group' => [static fn (ScoutEngine $engine): ScoutEngine => $engine->groupable(['active', 'active']), InvalidTextSearchException::duplicateAttribute(VoucherResource::class, 'active')],
        ];
    }

    #[Test]
    public function it_needs_a_decimal_cast_to_measure_a_number_on_an_engine_that_stores_decimals_with_limited_precision(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title', 'scout.typesense.model-settings.'.CentsVoucher::class.'.search-parameters.query_by' => 'title']);
        $declared = $this->resource(VoucherResource::class)->definition()->fields();
        $cents = $this->open(['price', 'number'])->measurable(['price', 'number'])->capabilities(new TextTarget(VoucherResource::class, FieldSet::of([]), new CentsVoucher, $declared));

        $this->assertSame(['price' => 2], $cents->engine?->totals?->scales);
        $this->expectExceptionObject(InvalidTextSearchException::unscaledMeasure(VoucherResource::class, 'price', Voucher::class, FakeTypesenseEngine::class));

        $this->capabilities($this->open(['price'])->measurable(['price']));
    }

    #[Test]
    public function it_refuses_to_group_the_document_id_on_typesense(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);

        $this->expectExceptionObject(InvalidTextSearchException::ungroupableAttributeType(VoucherResource::class, 'id', 'id', FakeTypesenseEngine::class));

        $this->capabilities($this->open(['id', 'active'])->groupable(['id']));
    }

    #[Test]
    public function it_refuses_totals_on_an_engine_that_computes_none(): void
    {
        config(['rest.extensions' => [SqlForeignEngineProfiles::class]]);
        $this->useEngine(new ForeignEngine);

        $this->expectExceptionObject(InvalidTextSearchException::untotalledEngine(VoucherResource::class, ForeignEngine::class));

        $this->capabilities($this->open(['active'])->groupable(['active']));
    }

    #[Test]
    public function it_refuses_a_typesense_model_that_sends_a_facet_parameter_of_a_resource_with_totals(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        $declared = $this->resource(VoucherResource::class)->definition()->fields();
        $target = new TextTarget(VoucherResource::class, FieldSet::of([]), new FacetingVoucher, $declared);

        $this->open(['active'])->capabilities($target);
        $this->expectExceptionObject(InvalidTextSearchException::ownedSearchParameter(VoucherResource::class, FacetingVoucher::class, 'facet_by'));

        $this->open(['active'])->groupable(['active'])->capabilities($target);
    }
}
