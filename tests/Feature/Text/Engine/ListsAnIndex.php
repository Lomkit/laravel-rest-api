<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EitherEngineRestriction;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TiedVoucherController;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

trait ListsAnIndex
{
    use ResetsExtensionStubs;

    #[Before]
    public function registerTheListedResources(): void
    {
        $this->afterApplicationCreated(fn () => Route::prefix('api')->group(function (): void {
            Rest::resource('listed-vouchers', ListedVoucherController::class)->withSoftDeletes();
            Rest::resource('listed-team-vouchers', ListedTeamVoucherController::class)->withSoftDeletes();
            Rest::resource('tied-vouchers', TiedVoucherController::class)->withSoftDeletes();
        }));
    }

    #[Test]
    public function it_lists_every_row_without_text_in_key_order_with_the_engine_total(): void
    {
        $response = $this->search('listed-vouchers', ['source' => 'engine']);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 2, 3], 5));
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('listSearches')]
    public function it_filters_sorts_and_pages_a_list_on_the_engine(array $search, array $ids, int $total, int $perPage, int $page): void
    {
        $response = $this->search('listed-vouchers', ['source' => 'engine', ...$search]);

        $response->assertOk()->assertExactJson($this->page($ids, $total, $perPage, $page));
    }

    /** @return array<string, array{array<string, mixed>, list<int>, int, int, int}> */
    public static function listSearches(): array
    {
        return [
            'integers in a list' => [['filters' => [['field' => 'number', 'operator' => 'in', 'value' => [1, 3]]]], [1, 3], 2, 50, 1],
            'a number below a bound' => [['filters' => [['field' => 'price', 'operator' => '<', 'value' => 12]]], [1, 3], 2, 50, 1],
            'a sort in descending order' => [['sorts' => [['field' => 'price', 'direction' => 'desc']]], [5, 2, 4, 3, 1], 5, 50, 1],
            'the second page' => [['limit' => 2, 'page' => 2], [1, 2], 5, 2, 2],
            'the trashed rows' => [['trashed' => 'with'], [6, 5, 4, 1, 2, 3], 6, 50, 1],
        ];
    }

    #[Test]
    public function it_keeps_a_list_inside_the_index_perimeter(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['blue']]));

        $response = $this->search('listed-team-vouchers', ['source' => 'engine']);

        $response->assertOk()->assertExactJson($this->page([4], 1));
    }

    #[Test]
    public function it_never_returns_a_row_outside_the_index_perimeter_through_an_or_filter(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        $filters = [['field' => 'number', 'value' => 4], ['field' => 'number', 'value' => 3, 'type' => 'or']];

        $list = $this->search('listed-team-vouchers', ['source' => 'engine', 'filters' => $filters]);
        $text = $this->search('listed-team-vouchers', ['text' => ['value' => 'report'], 'filters' => $filters]);

        $list->assertOk()->assertExactJson($this->page([1], 1));
        $text->assertOk()->assertExactJson($this->page([1], 1));
    }

    #[Test]
    public function it_lists_with_a_nested_group_of_filters(): void
    {
        $response = $this->search('listed-vouchers', ['source' => 'engine', 'filters' => [['field' => 'active', 'value' => true], ['nested' => [['field' => 'number', 'value' => 3], ['field' => 'price', 'operator' => '>', 'value' => 20, 'type' => 'or']]]]]);

        $response->assertOk()->assertExactJson($this->page([1, 2], 2));
    }

    #[Test]
    public function it_orders_a_list_on_a_string_tie_breaker_alone_and_behind_the_requested_sort(): void
    {
        $alone = $this->search('tied-vouchers', ['source' => 'engine']);
        $behind = $this->search('tied-vouchers', ['source' => 'engine', 'sorts' => [['field' => 'team']]]);

        $alone->assertOk()->assertExactJson($this->page([5, 4, 3, 1, 2], 5));
        $behind->assertOk()->assertExactJson($this->page([4, 5, 3, 1, 2], 5));
    }

    #[Test]
    public function it_never_returns_a_row_that_the_perimeter_or_another_restriction_excludes_through_an_extension_group(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
        config(['rest.extensions' => [EitherEngineRestriction::class]]);
        $grouped = $this->search('listed-team-vouchers', ['source' => 'engine']);
        config(['rest.extensions' => [CheapEngineRestriction::class, EitherEngineRestriction::class]]);

        $restricted = $this->search('listed-team-vouchers', ['source' => 'engine', 'filters' => [['field' => 'number', 'value' => 5], ['field' => 'number', 'value' => 4, 'type' => 'or'], ['field' => 'number', 'value' => 2, 'type' => 'or']]]);

        $grouped->assertOk()->assertExactJson($this->page([5, 2], 2));
        $restricted->assertOk()->assertExactJson($this->page([2], 1));
    }
}
