<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use LogicException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\EarlyReadingMutateRequest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\FailureIgnoringMutateRequest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\InputReplacingCategoryController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PayloadReadingMutateRequest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PricedCategoryController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RepeatedRulesMutateRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MutationPayloadTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PayloadReadingMutateRequest::$read = null;
        $this->app->bind(MutateRequest::class, PayloadReadingMutateRequest::class);
        Rest::resource('priced-categories', PricedCategoryController::class)->register();
    }

    #[Test]
    public function it_reads_the_mutation_as_typed_nodes(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        Product::factory()->create(['category_id' => $lighting->id, 'name' => 'Desk lamp']);
        $floorLamp = Product::factory()->create(['category_id' => $lighting->id, 'name' => 'Floor lamp']);

        $response = $this->postJson('/priced-categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating'], 'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => ['name' => 'Office chair', 'price' => 149]],
                ['operation' => 'attach', 'key' => [$floorLamp->id]],
            ]]],
            ['operation' => 'update', 'key' => (string) $lighting->id, 'relations' => ['featuredProducts' => [
                ['operation' => 'sync', 'key' => [$floorLamp->id], 'pivot' => ['price' => 10], 'without_detaching' => true],
            ]]],
        ]]);

        $seating = Category::query()
            ->where('name', 'Seating')
            ->sole();
        $chair = Product::query()
            ->where('name', 'Office chair')
            ->sole();
        $response->assertOk()->assertExactJson(['operations' => [
            ['operation' => 'create', 'key' => $seating->id, 'relations' => ['products' => [
                ['operation' => 'create', 'key' => $chair->id],
                ['operation' => 'attach', 'key' => [$floorLamp->id]],
            ]]],
            ['operation' => 'update', 'key' => $lighting->id, 'relations' => ['featuredProducts' => [
                ['operation' => 'sync', 'key' => [$floorLamp->id]],
            ]]],
        ]]);
        [$created, $updated] = $this->read();
        $this->assertSame([MutationOperation::Create, null, ['name' => 'Seating'], 0], [$created->operation, $created->key, $created->attributes, $created->depth]);
        $products = $created->relations['products'] ?? null;
        $this->assertIsArray($products);
        $this->assertSame([[MutationOperation::Create, null, 1], [MutationOperation::Attach, [$floorLamp->id], 1]], array_map(
            fn (MutationNode $node): array => [$node->operation, $node->key, $node->depth],
            $products,
        ));
        $this->assertNull($products[1]->attributes);
        $this->assertSame([MutationOperation::Update, $lighting->id], [$updated->operation, $updated->key]);
        $sync = ($updated->relations['featuredProducts'] ?? [])[0] ?? null;
        $this->assertInstanceOf(MutationNode::class, $sync);
        $this->assertSame([MutationOperation::Sync, [$floorLamp->id], ['price' => 10], true], [$sync->operation, $sync->key, $sync->pivot, $sync->withoutDetaching]);
        $featured = $lighting->featuredProducts()
            ->pluck('products.id')
            ->all();
        $this->assertSame($seating->id, $floorLamp->refresh()->category_id);
        $this->assertSame([$floorLamp->id], $featured);
    }

    #[Test]
    public function it_executes_the_parsed_mutation_and_not_the_request_input(): void
    {
        Rest::resource('input-replacing-categories', InputReplacingCategoryController::class)->register();

        $response = $this->postJson('/input-replacing-categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating']],
        ]]);

        $seating = Category::query()->sole();
        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => $seating->id]]]);
        $this->assertSame(['Seating'], $this->names());
    }

    #[Test]
    public function it_reads_the_mutation_once_when_the_rules_are_read_again(): void
    {
        config(['rest.limits.totals.entries' => 2]);
        $this->app->bind(MutateRequest::class, RepeatedRulesMutateRequest::class);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating']],
            ['operation' => 'create', 'attributes' => ['name' => 'Desks']],
        ]]);

        $response->assertOk();
        $this->assertSame([['name' => 'Seating'], ['name' => 'Desks']], array_map(
            fn (MutationNode $node): ?array => $node->attributes,
            PayloadReadingMutateRequest::$read ?? [],
        ));
        $this->assertSame(['Desks', 'Seating'], $this->names());
    }

    #[Test]
    public function it_refuses_to_read_the_mutation_before_validation(): void
    {
        $this->expectExceptionObject(new LogicException('The mutation was read before the request passed validation.'));

        (new MutateRequest)->payload();
    }

    #[Test]
    public function it_refuses_to_read_the_mutation_while_the_request_validates(): void
    {
        $this->app->bind(MutateRequest::class, EarlyReadingMutateRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The mutation was read before the request passed validation.'));

        $this->postJson('/api/categories/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['name' => 'Seating']]]]);
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('failedMutations')]
    public function it_refuses_to_read_a_mutation_that_failed_validation(array $mutate): void
    {
        $this->app->bind(MutateRequest::class, FailureIgnoringMutateRequest::class);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The mutation was read before the request passed validation.'));

        $this->postJson('/api/categories/mutate', ['mutate' => $mutate]);
    }

    /** @return array<string, array{list<array<string, mixed>>}> */
    public static function failedMutations(): array
    {
        return [
            'an operation that did not parse' => [[['operation' => 'replace']]],
            'a recorded violation' => [[['operation' => 'create', 'attributes' => ['name' => 'Seating'], 'pivot' => []]]],
        ];
    }

    /** @return array{MutationNode, MutationNode} */
    private function read(): array
    {
        $read = PayloadReadingMutateRequest::$read;
        $this->assertIsArray($read);
        $this->assertCount(2, $read);

        return [$read[0], $read[1]];
    }

    /** @return array<int, mixed> */
    private function names(): array
    {
        return Category::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
