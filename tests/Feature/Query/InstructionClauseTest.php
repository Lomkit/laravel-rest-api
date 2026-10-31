<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\InstructionClause;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\PricedBetween;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class InstructionClauseTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_passes_the_cast_and_defaulted_values_to_the_instruction(): void
    {
        $query = Post::query();

        (new InstructionClause($this->resource(PostResource::class), 'highlight-posts', [['name' => 'min_views', 'value' => '150']]))->apply($query);

        $this->assertSame([true, 150], $query->getBindings());
    }

    #[Test]
    public function it_applies_the_instruction_to_the_query_of_a_relation(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);
        $products = $lighting->products();

        (new InstructionClause($this->resource(ProductResource::class), 'priced-at', [['name' => 'price', 'value' => '129']]))->apply($products);

        $this->assertSame(['Floor lamp'], $products->pluck('name')->all());
    }

    #[Test]
    public function it_refuses_an_instruction_the_resource_does_not_declare(): void
    {
        $this->expectException(InvalidInstructionException::class);
        $this->expectExceptionMessage(PostResource::class.' does not declare the instruction [archive-posts].');

        (new InstructionClause($this->resource(PostResource::class), 'archive-posts'))->apply(Post::query());
    }

    #[Test]
    public function it_refuses_to_apply_an_instruction_that_only_applies_to_a_search_engine(): void
    {
        $query = Product::query();

        $this->expectExceptionObject(InvalidInstructionException::engineOnly(PricedBetween::class));

        (new InstructionClause($this->resource(ProductResource::class), 'priced-between', [['name' => 'max', 'value' => '39']]))->apply($query);
    }

    /** @param class-string<Resource> $resource */
    private function resource(string $resource): Resource
    {
        return (new RestContext(RestRequest::create('/api/search', 'POST'), $this->app))->resource($resource);
    }
}
