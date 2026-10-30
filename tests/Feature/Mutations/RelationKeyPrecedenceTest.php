<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\ClearanceProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationKeyPrecedenceTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('clearance-products', ClearanceProductController::class)->register();
    }

    #[Test]
    public function it_keeps_the_key_that_the_mutating_hook_sets_over_the_belongs_to_relation_and_the_attribute(): void
    {
        $clearance = Category::factory()->create(['name' => 'Clearance']);
        $seating = Category::factory()->create(['name' => 'Seating']);

        $response = $this->postJson('/clearance-products/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Stool', 'price' => 45, 'category_id' => $seating->id],
            'relations' => ['category' => ['operation' => 'attach', 'key' => $seating->id]],
        ]]]);

        $stool = Product::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$stool->id], 'updated' => []]);
        $this->assertSame($clearance->id, $stool->category_id);
    }

    #[Test]
    public function it_keeps_the_parent_key_of_a_has_many_relation_over_the_belongs_to_relation_of_the_entry(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $seating = Category::factory()->create(['name' => 'Seating']);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $lighting->id,
            'relations' => ['products' => [[
                'operation' => 'create',
                'attributes' => ['name' => 'Lamp', 'price' => 30],
                'relations' => ['category' => ['operation' => 'attach', 'key' => $seating->id]],
            ]]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$lighting->id]]);
        $this->assertSame(['Lamp' => $lighting->id], Product::query()->pluck('category_id', 'name')->all());
    }

    #[Test]
    public function it_keeps_the_parent_key_of_a_has_one_relation_over_the_belongs_to_relation_of_the_entry(): void
    {
        $emma = Book::query()->create(['title' => 'Emma']);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Dune'],
            'relations' => ['loan' => [
                'operation' => 'create',
                'attributes' => ['borrower' => 'Grace'],
                'relations' => ['book' => ['operation' => 'attach', 'key' => $emma->id]],
            ]],
        ]]]);

        $dune = Book::query()->where('title', 'Dune')->sole();
        $response->assertOk()->assertExactJson(['created' => [$dune->id], 'updated' => []]);
        $this->assertSame(['Grace' => $dune->id], Loan::query()->pluck('book_id', 'borrower')->all());
    }

    #[Test]
    public function it_keeps_the_parent_keys_of_a_morph_many_relation_over_the_morph_to_relation_of_the_entry(): void
    {
        $emma = Book::query()->create(['title' => 'Emma']);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Dune'],
            'relations' => ['labels' => [[
                'operation' => 'create',
                'attributes' => ['text' => 'Classic'],
                'relations' => ['labelable' => ['operation' => 'attach', 'key' => $emma->id]],
            ]]],
        ]]]);

        $dune = Book::query()->where('title', 'Dune')->sole();
        $response->assertOk()->assertExactJson(['created' => [$dune->id], 'updated' => []]);
        $this->assertSame(['labelable_id' => $dune->id, 'labelable_type' => Book::class], Label::query()->sole()->only('labelable_id', 'labelable_type'));
    }
}
