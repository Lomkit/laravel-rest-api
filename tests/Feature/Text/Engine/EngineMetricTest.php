<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\MeasuredVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineMetricTest extends EngineSearchTestCase
{
    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
        Route::prefix('api')->group(fn () => Rest::resource('measured-vouchers', MeasuredVoucherController::class)->register());
    }

    /** @param array<string, string> $text */
    #[Test]
    #[DataProvider('texts')]
    public function it_refuses_a_text_on_a_metric_of_a_resource_searched_on_an_engine(array $text): void
    {
        $this->postJson('/api/measured-vouchers/metrics/row-count', ['search' => ['text' => $text]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The search.text field is prohibited.', 'errors' => ['search.text' => ['The search.text field is prohibited.']]]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function texts(): iterable
    {
        yield 'a word' => [['value' => 'report']];
        yield 'a blank text' => [['value' => ' ']];
    }

    #[Test]
    public function it_counts_a_metric_of_a_resource_searched_on_an_engine_in_the_database(): void
    {
        $this->postJson('/api/measured-vouchers/metrics/row-count', ['search' => ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Report%']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'count', 'value' => 3]]);
        $this->assertSame([], $this->engine->searches);
    }
}
