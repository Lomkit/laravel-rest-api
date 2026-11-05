<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\InstructionClause;
use Lomkit\Rest\Search\InstructionNode;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
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
    use ParsesSearchNodes;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_passes_the_cast_and_defaulted_values_to_the_instruction(): void
    {
        $query = Post::query();

        InstructionClause::of($this->instruction(PostResource::class, 'highlight-posts', [['name' => 'min_views', 'value' => '150']]))->apply($query);

        $this->assertSame([true, 150], $query->getBindings());
    }

    #[Test]
    public function it_applies_the_instruction_to_the_query_of_a_relation(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);
        $products = $lighting->products();

        InstructionClause::of($this->instruction(ProductResource::class, 'priced-at', [['name' => 'price', 'value' => '129']]))->apply($products);

        $this->assertSame(['Floor lamp'], $products->pluck('name')->all());
    }

    #[Test]
    public function it_refuses_to_apply_an_instruction_that_only_applies_to_a_search_engine(): void
    {
        $query = Product::query();
        $resource = $this->resource(ProductResource::class);
        $instruction = $resource->definition()->instruction('priced-between');
        $this->assertInstanceOf(PricedBetween::class, $instruction);
        $node = new InstructionNode($instruction, InputValues::fromNamed($resource->definition()->inputFields($instruction), [['name' => 'max', 'value' => '39']]));

        $this->expectExceptionObject(InvalidInstructionException::engineOnly(PricedBetween::class));

        InstructionClause::of($node)->apply($query);
    }

    /**
     * @param  class-string<Resource>  $resource
     * @param  list<array{name: string, value: mixed}>  $fields
     */
    private function instruction(string $resource, string $name, array $fields): InstructionNode
    {
        return $this->parse($this->resource($resource), ['instructions' => [['name' => $name, 'fields' => $fields]]])->instructions[0];
    }

    /** @param class-string<Resource> $resource */
    private function resource(string $resource): Resource
    {
        return (new RestContext(RestRequest::create('/api/search', 'POST'), $this->app))->resource($resource);
    }
}
