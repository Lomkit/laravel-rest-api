<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs\TotalledVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineTotalsTest extends EngineSearchTestCase
{
    private const array TOTALS = [
        'measures' => [
            ['type' => 'sum', 'field' => 'price'],
            ['type' => 'avg', 'field' => 'price'],
            ['type' => 'min', 'field' => 'number'],
            ['type' => 'max', 'field' => 'number'],
            ['type' => 'sum', 'field' => 'number'],
        ],
        'groups' => [['field' => 'team'], ['field' => 'active'], ['field' => 'number', 'limit' => 2]],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(static function (): void {
            Rest::resource('totalled-vouchers', TotalledVoucherController::class)->register();
            Rest::resource('plain-engine-vouchers', PlainEngineVoucherController::class)->register();
        });
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_computes_the_totals_of_an_engine_list_on_the_rows_that_the_driver_matches(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => self::TOTALS]);

        $response->assertOk()->assertJsonPath('total', 5);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 5, 'sum_price' => 90.75, 'avg_price' => 18.15, 'min_number' => 1, 'max_number' => 5, 'sum_number' => 15],
            'groups' => [
                ['field' => 'team', 'buckets' => [['value' => 'red', 'count' => 4], ['value' => 'blue', 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'active', 'buckets' => [['value' => true, 'count' => 4], ['value' => false, 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false],
                ['field' => 'number', 'buckets' => [['value' => 1, 'count' => 1], ['value' => 2, 'count' => 1]], 'other' => ['count' => 3], 'truncated' => true],
            ],
        ], $response->json('totals'));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_counts_the_rows_that_the_filters_and_the_text_keep(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('totalled-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'active', 'value' => true]], 'totals' => ['measures' => [['type' => 'sum', 'field' => 'price']], 'groups' => [['field' => 'team']]]]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 3, 'sum_price' => 20.75],
            'groups' => [['field' => 'team', 'buckets' => [['value' => 'red', 'count' => 2], ['value' => 'blue', 'count' => 1]], 'other' => ['count' => 0], 'truncated' => false]],
        ], $response->json('totals'));
    }

    #[Test]
    public function it_returns_empty_totals_on_the_null_driver(): void
    {
        config(['scout.driver' => 'null']);

        $response = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['measures' => [['type' => 'sum', 'field' => 'number'], ['type' => 'avg', 'field' => 'price']], 'groups' => [['field' => 'team']]]]);

        $response->assertOk()->assertJsonPath('total', 0);
        $this->assertSame([
            'exact' => true,
            'all' => ['count' => 0, 'sum_number' => 0, 'avg_price' => null],
            'groups' => [['field' => 'team', 'buckets' => [], 'other' => ['count' => 0], 'truncated' => false]],
        ], $response->json('totals'));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_answers_an_engine_search_as_its_engine_grammar_does(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $undeclared = $this->search('totalled-vouchers', ['source' => 'engine', 'totals' => ['groups' => [['field' => 'code']], 'measures' => [['type' => 'sum', 'field' => 'active']]]]);
        $nothing = $this->search('vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team']]]]);
        $sql = $this->search('vouchers', ['totals' => ['groups' => [['field' => 'team']]]]);

        $undeclared->assertUnprocessable()->assertJsonPath('errors', [
            'search.totals.measures.0.field' => ['The selected search.totals.measures.0.field is invalid.'],
            'search.totals.groups.0.field' => ['The selected search.totals.groups.0.field is invalid.'],
        ]);
        $nothing->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited.']]);
        $sql->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited.']]);
    }

    #[Test]
    public function it_refuses_totals_on_an_engine_that_computes_none(): void
    {
        $response = $this->search('plain-engine-vouchers', ['text' => ['value' => 'report'], 'totals' => ['groups' => [['field' => 'team']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is refused: the search engine of this resource computes no totals.']]);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_describes_the_totals_of_the_engine_in_the_details(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $totals = $this->getJson('/api/totalled-vouchers?with=text')->assertOk()->json('data.text.engine.totals');
        $plain = $this->getJson('/api/vouchers?with=text')->assertOk()->json('data.text.engine');

        $this->assertSame(['groups' => ['team', 'active', 'number'], 'measures' => ['price' => ['sum', 'avg', 'min', 'max'], 'number' => ['sum', 'avg', 'min', 'max']]], $totals);
        $this->assertArrayNotHasKey('totals', $plain);
    }
}
