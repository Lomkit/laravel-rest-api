<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Lomkit\Rest\Query\ScoutBuilder;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextSearchProductsTest extends PackageTestCase
{
    private Category $lighting;

    private Product $deskLamp;

    private Product $floorLamp;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->lighting = Category::factory()->create(['name' => 'Lighting']);
        $this->deskLamp = Product::factory()->for($this->lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        $this->floorLamp = Product::factory()->for($this->lighting)->create(['name' => 'Floor lamp', 'price' => 129]);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_returns_the_products_matching_a_text_search(): void
    {
        $response = $this->postJson('/api/products/search', [
            'search' => ['text' => ['value' => 'floor']],
        ]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->floorLamp)]));
    }

    #[Test]
    public function it_filters_a_text_search_on_a_scout_field(): void
    {
        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => ['value' => 'lamp'],
                'filters' => [['field' => 'price', 'value' => 129]],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->floorLamp)]));
    }

    #[Test]
    public function it_sorts_a_text_search_on_a_scout_field(): void
    {
        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => ['value' => 'lamp'],
                'sorts' => [['field' => 'price', 'direction' => 'asc']],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->deskLamp), $this->row($this->floorLamp)]));
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('blankTexts')]
    public function it_runs_the_normal_search_for_a_blank_text(array $text): void
    {
        $this->app->bind(ScoutBuilder::class, fn (): ScoutBuilder => throw new LogicException('A blank text reached the index.'));

        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => $text,
                'filters' => [['field' => 'name', 'operator' => 'like', 'value' => 'Desk%']],
            ],
        ]);

        $response->assertOk()->assertExactJson($this->page([$this->row($this->deskLamp)]));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function blankTexts(): array
    {
        return [
            'an empty value' => [['value' => '']],
            'a value made of spaces' => [['value' => '   ']],
            'a null value' => [['value' => null]],
            'no value' => [[]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searchesDeniedByViewAny')]
    public function it_checks_view_any_before_any_search_runs(array $search): void
    {
        Gate::before(fn (?Authenticatable $user): bool => false);

        $response = $this->postJson('/api/products/search', ['search' => $search]);

        $response->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function searchesDeniedByViewAny(): array
    {
        return [
            'a text without hits' => [['text' => ['value' => 'sofa']]],
            'a page past the last one' => [['text' => ['value' => 'lamp'], 'page' => 999]],
            'a blank text' => [['text' => ['value' => '']]],
        ];
    }

    /** @return array<string, int|string> */
    private function row(Product $product): array
    {
        return ['id' => $product->id, 'category_id' => $this->lighting->id, 'name' => $product->name, 'price' => $product->price];
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     * @return array<string, mixed>
     */
    private function page(array $rows): array
    {
        return [
            'current_page' => 1,
            'data' => $rows,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($rows),
            'total' => count($rows),
            'meta' => [],
        ];
    }
}
