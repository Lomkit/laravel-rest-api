<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use LogicException;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\InstructionClause;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\HighlightPosts;
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
    public function it_applies_a_scout_instruction_to_a_scout_query(): void
    {
        $query = Product::search('lamp');

        (new InstructionClause($this->resource(ProductResource::class), 'priced-at', [['name' => 'price', 'value' => '129']]))->applyToScout($query);

        $this->assertSame([['field' => 'price', 'operator' => '=', 'value' => 129]], $query->wheres);
    }

    #[Test]
    public function it_refuses_an_instruction_the_resource_does_not_declare(): void
    {
        $this->expectException(InvalidInstructionException::class);
        $this->expectExceptionMessage(PostResource::class.' does not declare the instruction [archive-posts].');

        (new InstructionClause($this->resource(PostResource::class), 'archive-posts'))->apply(Post::query());
    }

    #[Test]
    public function it_refuses_a_scout_instruction_the_resource_does_not_declare(): void
    {
        $this->expectException(InvalidInstructionException::class);
        $this->expectExceptionMessage(PostResource::class.' does not declare the Scout instruction [highlight-posts].');

        (new InstructionClause($this->resource(PostResource::class), 'highlight-posts'))->applyToScout(Product::search('lamp'));
    }

    #[Test]
    public function it_refuses_scout_queries_unless_the_instruction_handles_them(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(HighlightPosts::class.' does not support Scout queries. Override handleScout() to support them.');

        HighlightPosts::make()->handleScout([], Product::search('lamp'));
    }

    /** @param class-string<Resource> $resource */
    private function resource(string $resource): Resource
    {
        return (new RestContext(RestRequest::create('/api/search', 'POST'), $this->app))->resource($resource);
    }
}
