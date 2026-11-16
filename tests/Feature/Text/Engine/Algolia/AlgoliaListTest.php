<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Algolia;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedUnsortedVoucherController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('algolia')]
class AlgoliaListTest extends AlgoliaTestCase
{
    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('listed-unsorted-vouchers', ListedUnsortedVoucherController::class)->withSoftDeletes());
    }

    #[Test]
    public function it_lists_with_an_empty_query_the_filters_and_no_sort(): void
    {
        $this->hits(['V-a', 'V-b'], 5, 1, 2);

        $response = $this->search('listed-unsorted-vouchers', ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 40]], 'limit' => 2, 'page' => 2]);

        $response->assertOk()->assertExactJson($this->page([3, 2], 5, 2, 2));
        $this->assertSame([['POST', "/1/indexes/{$this->index}/query", [
            'query' => '',
            'filters' => "__soft_deleted:'0' AND price<40",
            'hitsPerPage' => 2,
            'page' => 1,
        ]]], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }

    #[Test]
    public function it_refuses_a_sort_in_a_list_on_algolia(): void
    {
        $response = $this->search('listed-unsorted-vouchers', ['sorts' => [['field' => 'price']]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.sorts.0.field' => ['The search.sorts.0.field field is refused: this resource serves its lists through its search engine, which does not run it.']]);
        $this->assertSame([], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }

    #[Test]
    public function it_refuses_or_and_nested_filters_on_algolia(): void
    {
        $either = $this->search('listed-unsorted-vouchers', ['filters' => [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 4, 'type' => 'or']]]);
        $nested = $this->search('listed-unsorted-vouchers', ['filters' => [['nested' => [['field' => 'number', 'value' => 3]]]]]);

        $either->assertUnprocessable()->assertJsonPath('errors', ['search.filters.1.type' => ['The selected search.filters.1.type is invalid.']]);
        $nested->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.field' => ['The search.filters.0.field field is required.'],
            'search.filters.0.nested' => ['The search.filters.0.nested field is prohibited.'],
        ]);
        $this->assertSame([], $this->algolia->sent('POST', "/1/indexes/{$this->index}/query"));
    }
}
