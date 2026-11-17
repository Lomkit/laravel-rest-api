<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

trait ListsAnIndex
{
    #[Before]
    public function registerTheListedResources(): void
    {
        $this->afterApplicationCreated(fn () => Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->withSoftDeletes();
            Rest::resource('listed-team-vouchers', ListedTeamVoucherController::class)->withSoftDeletes();
        }));
    }

    #[Test]
    public function it_lists_every_row_without_text_in_key_order_with_the_engine_total(): void
    {
        $response = $this->search('listed-vouchers', []);

        $response->assertOk()->assertExactJson($this->page([3, 2, 1, 4, 5], 5));
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('listSearches')]
    public function it_filters_sorts_and_pages_a_list_on_the_engine(array $search, array $ids, int $total, int $perPage, int $page): void
    {
        $response = $this->search('listed-vouchers', $search);

        $response->assertOk()->assertExactJson($this->page($ids, $total, $perPage, $page));
    }

    /** @return array<string, array{array<string, mixed>, list<int>, int, int, int}> */
    public static function listSearches(): array
    {
        return [
            'integers in a list' => [['filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 3]]]], [3, 1], 2, 50, 1],
            'a number below a bound' => [['filters' => [['field' => 'price', 'operator' => '<', 'value' => 12]]], [3, 1], 2, 50, 1],
            'a sort in descending order' => [['sorts' => [['field' => 'price', 'direction' => 'desc']]], [5, 2, 4, 3, 1], 5, 50, 1],
            'the second page' => [['limit' => 2, 'page' => 2], [1, 4], 5, 2, 2],
            'the trashed rows' => [['trashed' => 'with'], [3, 2, 1, 4, 5, 6], 6, 50, 1],
        ];
    }

    #[Test]
    public function it_keeps_a_list_inside_the_index_perimeter(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['blue']]));

        $response = $this->search('listed-team-vouchers', []);

        $response->assertOk()->assertExactJson($this->page([4], 1));
    }

    #[Test]
    public function it_never_returns_a_row_outside_the_index_perimeter_through_an_or_filter(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        $filters = [['field' => 'number', 'value' => 4], ['field' => 'number', 'value' => 3, 'type' => 'or']];

        $list = $this->search('listed-team-vouchers', ['filters' => $filters]);
        $text = $this->search('listed-team-vouchers', ['text' => ['value' => 'report'], 'filters' => $filters]);

        $list->assertOk()->assertExactJson($this->page([1], 1));
        $text->assertOk()->assertExactJson($this->page([1], 1));
    }

    #[Test]
    public function it_lists_with_a_nested_group_of_filters(): void
    {
        $response = $this->search('listed-vouchers', ['filters' => [['field' => 'active', 'value' => true], ['nested' => [['field' => 'number', 'value' => 3], ['field' => 'price', 'operator' => '>', 'value' => 20, 'type' => 'or']]]]]);

        $response->assertOk()->assertExactJson($this->page([2, 1], 2));
    }
}
