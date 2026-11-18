<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CuratingVoucher;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CuratingVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\CuratingVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GroupedReaderVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedUnsortedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class CuratedHitsTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('reader-vouchers', ReaderVoucherController::class)->register();
            Rest::resource('grouped-reader-vouchers', GroupedReaderVoucherController::class)->register();
            Rest::resource('listed-unsorted-vouchers', ListedUnsortedVoucherController::class)->register();
            Rest::resource('curating-vouchers', CuratingVoucherController::class)->register();
        });
        config([
            'rest.extensions' => [ReadersExtension::class],
            'scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title',
        ]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
    }

    #[Test]
    public function it_filters_the_curated_hits_of_a_text_search_an_engine_list_and_the_key_reads_of_an_action_on_typesense(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);

        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();
        $this->search('grouped-reader-vouchers', ['source' => 'engine'])->assertOk();
        $this->postJson('/api/reader-vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]])->assertOk();

        $this->assertCount(3, $engine->parameters);
        $this->assertSame([true, true, true], array_column($engine->parameters, 'filter_curated_hits'));
    }

    /** @param class-string $engine */
    #[Test]
    #[DataProvider('ownedOptions')]
    public function it_refuses_an_option_that_lets_hits_escape_the_filter(string $engine, string $option, mixed $value): void
    {
        $this->useEngine($engine === FakeTypesenseEngine::class ? new FakeTypesenseEngine : new FilterCapturingAlgolia4Engine(new SettingsClient(fn (): array => [])));
        $text = $this->resource(ReaderVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        $this->expectExceptionObject(InvalidEngineQueryException::ownedOption($option));

        $text->query(new TextQuery('report'), $this->target(ReaderVoucherResource::class))->options([$option => $value]);
    }

    /** @return array<string, array{class-string, string, mixed}> */
    public static function ownedOptions(): array
    {
        return [
            'the curated hits of typesense' => [FakeTypesenseEngine::class, 'filter_curated_hits', false],
            'a natural language query of typesense' => [FakeTypesenseEngine::class, 'nl_query', true],
            'the rules of algolia' => [FilterCapturingAlgolia4Engine::class, 'enableRules', true],
        ];
    }

    #[Test]
    public function it_refuses_a_typesense_model_that_turns_off_the_filter_of_curated_hits(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::ownedSearchParameter(CuratingVoucherResource::class, CuratingVoucher::class, 'filter_curated_hits'));

        $this->search('curating-vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_refuses_a_typesense_model_whose_parameters_change_after_the_check(): void
    {
        $this->useEngine(new FilterCapturingTypesenseEngine);
        CuratingVoucher::$curates = false;
        $text = $this->resource(CuratingVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);
        $query = $text->query(new TextQuery('report'), $this->target(CuratingVoucherResource::class));
        CuratingVoucher::$curates = true;

        $this->expectExceptionObject(InvalidTextSearchException::changedSearchParameter(CuratingVoucher::class, 'filter_curated_hits'));

        $query->page(50, 1);
    }

    #[Test]
    public function it_turns_off_the_rules_of_algolia(): void
    {
        $engine = new FilterCapturingAlgolia4Engine(new SettingsClient(fn (): array => []));
        $this->useEngine($engine);

        $this->search('listed-unsorted-vouchers', ['text' => ['value' => 'report']])->assertOk();
        $this->search('listed-unsorted-vouchers', ['source' => 'engine'])->assertOk();

        $this->assertSame([['enableRules' => false], ['enableRules' => false]], $engine->options);
    }
}
