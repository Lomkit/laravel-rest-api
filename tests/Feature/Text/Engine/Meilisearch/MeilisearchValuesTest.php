<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TitledVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TitledVouchers;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchValuesTest extends MeilisearchTestCase
{
    private const array BY_PRICE = [['field' => 'price', 'direction' => 'asc']];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        TitledVouchers::seed();
        $this->settle();
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('titled-vouchers', TitledVoucherController::class));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('values')]
    public function it_matches_values_with_quotes_backslashes_commas_and_brackets_as_they_are(array $filter, array $ids): void
    {
        $response = $this->search('titled-vouchers', ['text' => ['value' => 'report'], 'filters' => [$filter], 'sorts' => self::BY_PRICE]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $ids)
            ->assertJsonPath('total', count($ids));
    }

    /** @return array<string, array{array<string, mixed>, list<int>}> */
    public static function values(): array
    {
        return [
            'a double quote' => [['field' => 'title', 'value' => TitledVouchers::QUOTED], [7]],
            'a backslash' => [['field' => 'title', 'value' => TitledVouchers::BACKSLASH], [8]],
            'a trailing backslash' => [['field' => 'title', 'value' => TitledVouchers::TRAILING], [9]],
            'a comma' => [['field' => 'title', 'value' => TitledVouchers::COMMA], [11]],
            'brackets' => [['field' => 'title', 'value' => TitledVouchers::BRACKETS], [12]],
            'a list' => [['field' => 'title', 'operator' => 'in', 'value' => [TitledVouchers::QUOTED, TitledVouchers::TRAILING, TitledVouchers::BRACKETS]], [7, 9, 12]],
            'an excluded list' => [['field' => 'title', 'operator' => 'not in', 'value' => [TitledVouchers::QUOTED, TitledVouchers::BACKSLASH, TitledVouchers::BRACKETS]], [1, 9, 11, 3, 5]],
        ];
    }

    #[Test]
    public function it_applies_an_instruction_that_passes_a_comparison_operator(): void
    {
        $response = $this->search('titled-vouchers', ['text' => ['value' => 'report'], 'sorts' => self::BY_PRICE, 'instructions' => [
            ['name' => 'compares-price', 'fields' => [['name' => 'operator', 'value' => '>='], ['name' => 'value', 'value' => 7]]],
        ]]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [3, 5])
            ->assertJsonPath('total', 2);
    }

    #[Test]
    public function it_unites_the_not_in_lists_of_the_perimeter_and_of_the_client(): void
    {
        $engine = ScoutEngine::make()
            ->filterable(['number', 'price', 'active', 'code', Text::make('team')])
            ->sortable(['price', 'number'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereNotIn('team', ['blue']));

        $page = $engine->query(new TextQuery('report'), $this->target(VoucherResource::class), $this->context()->request)
            ->whereNotIn('team', ['green'])
            ->orderBy('price')
            ->page(10, 1);

        $this->assertEquals(new EnginePage(['V-c', 'V-g', 'V-h', 'V-i', 'V-k', 'V-l', 'V-a', 'V-e'], 8), $page);
    }
}
