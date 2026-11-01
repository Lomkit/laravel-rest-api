<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia3Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\QueryByVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherLine;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\IndexAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ScoutEngineDeclarationTest extends ScoutEngineDeclarationTestCase
{
    #[Test]
    public function it_states_the_engine_grammar_of_its_declaration(): void
    {
        $capabilities = $this->capabilities(
            ScoutEngine::make()
                ->filterable(['number', 'price', 'active', 'code'])
                ->sortable(['price', 'number'])
                ->perimeter(fn (): null => null, [Text::make('team')]),
        );

        $this->assertSame([[], [TextMode::Words], true, false], [$capabilities->fields, $capabilities->modes, $capabilities->relevance, $capabilities->trashed]);
        $this->assertSame([
            'number' => ['=', '!=', '>', '>=', '<', '<=', 'in', 'not in'],
            'price' => ['=', '!=', '>', '>=', '<', '<=', 'in', 'not in'],
            'active' => ['=', '!='],
            'code' => ['=', '!=', 'in', 'not in'],
        ], array_map(fn (IndexAttribute $attribute): array => array_column($attribute->operators, 'value'), $capabilities->engine->filters ?? []));
        $this->assertSame(['price', 'number'], $capabilities->engine?->sorts);
    }

    #[Test]
    public function it_narrows_the_operators_of_a_field_to_its_own(): void
    {
        $capabilities = $this->capabilities($this->open([Integer::make('number')->filterable(['=', 'between', 'in'])]));

        $this->assertSame([FilterOperator::Equal, FilterOperator::In], $capabilities->engine?->operators('number'));
    }

    #[Test]
    public function it_searches_the_trashed_models_when_the_index_keeps_them(): void
    {
        config(['scout.soft_delete' => true]);

        $this->assertTrue($this->capabilities(ScoutEngine::make()->withoutPerimeter())->trashed);
    }

    #[Test]
    public function it_needs_no_searchable_field(): void
    {
        $target = $this->target(VoucherResource::class);

        $this->assertSame([], $target->fields->names());
        $this->assertSame([], $this->open()->capabilities($target)->fields);
    }

    #[Test]
    public function it_refuses_a_declaration_without_index_perimeter(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::missingPerimeter(VoucherResource::class));

        $this->capabilities(ScoutEngine::make()->filterable(['price']));
    }

    #[Test]
    public function it_refuses_a_declaration_with_and_without_index_perimeter(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::conflictingPerimeter(VoucherResource::class));

        $this->capabilities(
            ScoutEngine::make()
                ->perimeter(fn (): null => null)
                ->withoutPerimeter(),
        );
    }

    #[Test]
    public function it_refuses_a_model_that_is_not_indexed(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::unsearchableModel(VoucherResource::class, VoucherLine::class));

        $this->open()->capabilities(new TextTarget(VoucherResource::class, FieldSet::of([]), new VoucherLine, FieldSet::of([])));
    }

    #[Test]
    public function it_refuses_an_engine_that_extends_no_scout_engine(): void
    {
        $this->useEngine(new ForeignEngine);

        $this->expectExceptionObject(InvalidTextSearchException::unsupportedEngine(VoucherResource::class, ForeignEngine::class));

        $this->capabilities(ScoutEngine::make()->withoutPerimeter());
    }

    #[Test]
    public function it_resolves_a_subclass_of_a_scout_engine(): void
    {
        $this->useEngine(new RecordingCollectionEngine);

        $capabilities = $this->capabilities($this->open(['price'], ['price']));

        $this->assertSame(['price'], $capabilities->engine?->sorts);
    }

    #[Test]
    #[DataProvider('unsortingEngines')]
    public function it_refuses_sorts_on_an_engine_that_does_not_sort_text_searches(string $engine): void
    {
        $instance = $engine === FakeTurbopufferEngine::class ? new FakeTurbopufferEngine : new $engine(new SettingsClient(fn (): array => []));
        $this->useEngine($instance);

        $this->assertSame([], $this->capabilities($this->open(['price']))->engine?->sorts);
        $this->expectExceptionObject(InvalidTextSearchException::unsortableEngine(VoucherResource::class, $engine));

        $this->capabilities($this->open(['price'], ['price']));
    }

    /** @return array<string, array{class-string}> */
    public static function unsortingEngines(): array
    {
        return [
            'algolia 3' => [FakeAlgolia3Engine::class],
            'algolia 4' => [FakeAlgolia4Engine::class],
            'turbopuffer' => [FakeTurbopufferEngine::class],
        ];
    }

    #[Test]
    public function it_refuses_typesense_without_the_attributes_to_search(): void
    {
        $this->useEngine(new FakeTypesenseEngine);

        $this->expectExceptionObject(InvalidTextSearchException::missingQueryBy(VoucherResource::class, Voucher::class));

        $this->capabilities(ScoutEngine::make()->withoutPerimeter());
    }

    #[Test]
    public function it_accepts_typesense_with_the_attributes_to_search(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title,code']);
        $resource = $this->resource(VoucherResource::class);
        $declared = $resource->getFields($resource->context()->request);

        $configured = $this->capabilities($this->open(['price'], ['price']));
        $declaredOnModel = $this->open()->capabilities(new TextTarget(VoucherResource::class, FieldSet::of([]), new QueryByVoucher, $declared));

        $this->assertSame(['price'], $configured->engine?->sorts);
        $this->assertSame([], $declaredOnModel->engine?->sorts);
    }
}
