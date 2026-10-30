<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class NestedForeignKeyTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Library];
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[Test]
    public function it_keeps_the_parent_key_of_a_has_many_relation_over_the_attribute(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $seating = Category::factory()->create(['name' => 'Seating']);
        $bench = Product::factory()->create(['category_id' => $seating->id, 'name' => 'Bench']);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $seating->id,
            'relations' => ['products' => [
                ['operation' => 'create', 'attributes' => ['name' => 'Stool', 'price' => 45, 'category_id' => $lighting->id]],
                ['operation' => 'update', 'key' => $bench->id, 'attributes' => ['category_id' => $lighting->id]],
            ]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$seating->id]]);
        $this->assertSame(['Bench' => $seating->id, 'Stool' => $seating->id], Product::query()->orderBy('name')->pluck('category_id', 'name')->all());
    }

    #[Test]
    public function it_keeps_the_foreign_key_of_a_belongs_to_relation_over_the_attribute(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $seating = Category::factory()->create(['name' => 'Seating']);

        $response = $this->postJson('/api/products/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Stool', 'price' => 45, 'category_id' => $lighting->id],
            'relations' => ['category' => ['operation' => 'attach', 'key' => $seating->id]],
        ]]]);

        $stool = Product::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$stool->id], 'updated' => []]);
        $this->assertSame($seating->id, $stool->category_id);
    }

    #[Test]
    public function it_keeps_the_keys_of_a_morph_to_relation_over_the_attributes(): void
    {
        $dune = Book::query()->create(['title' => 'Dune']);
        $emma = Book::query()->create(['title' => 'Emma']);

        $response = $this->postJson('/api/labels/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['text' => 'Classic', 'labelable_id' => $emma->id, 'labelable_type' => Shelf::class],
            'relations' => ['labelable' => ['operation' => 'attach', 'key' => $dune->id]],
        ]]]);

        $classic = Label::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$classic->id], 'updated' => []]);
        $this->assertSame([$dune->id, Book::class], [$classic->labelable_id, $classic->labelable_type]);
    }

    #[Test]
    #[DataProvider('loanRelations')]
    public function it_keeps_the_parent_key_of_a_has_one_relation_over_the_attribute(string $relation): void
    {
        $emma = Book::query()->create(['title' => 'Emma']);

        $response = $this->createDune([$relation => ['operation' => 'create', 'attributes' => ['borrower' => 'Grace', 'book_id' => $emma->id]]]);

        $dune = Book::query()->where('title', 'Dune')->sole();
        $response->assertOk()->assertExactJson(['created' => [$dune->id], 'updated' => []]);
        $this->assertSame($dune->id, Loan::query()->sole()->book_id);
    }

    /** @return array<string, array{string}> */
    public static function loanRelations(): array
    {
        return ['has one' => ['loan'], 'has one of many' => ['latestLoan']];
    }

    #[Test]
    #[DataProvider('labelRelations')]
    public function it_keeps_the_parent_keys_of_a_morph_relation_over_the_attributes(string $relation, bool $many): void
    {
        $emma = Book::query()->create(['title' => 'Emma']);
        $creation = ['operation' => 'create', 'attributes' => ['text' => 'Classic', 'labelable_id' => $emma->id, 'labelable_type' => Shelf::class]];

        $response = $this->createDune([$relation => $many ? [$creation] : $creation]);

        $dune = Book::query()->where('title', 'Dune')->sole();
        $response->assertOk()->assertExactJson(['created' => [$dune->id], 'updated' => []]);
        $this->assertSame(['labelable_id' => $dune->id, 'labelable_type' => Book::class], Label::query()->sole()->only('labelable_id', 'labelable_type'));
    }

    #[Test]
    #[DataProvider('labelRelations')]
    public function it_writes_the_morph_alias_of_the_parent_in_a_morph_relation(string $relation, bool $many): void
    {
        Relation::morphMap(['book' => Book::class]);
        $creation = ['operation' => 'create', 'attributes' => ['text' => 'Classic']];

        $response = $this->createDune([$relation => $many ? [$creation] : $creation]);

        $dune = Book::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$dune->id], 'updated' => []]);
        $this->assertSame(['labelable_id' => $dune->id, 'labelable_type' => 'book'], Label::query()->sole()->only('labelable_id', 'labelable_type'));
    }

    /** @return array<string, array{string, bool}> */
    public static function labelRelations(): array
    {
        return [
            'morph one' => ['label', false],
            'morph one of many' => ['latestLabel', false],
            'morph many' => ['labels', true],
        ];
    }

    /** @param array<string, mixed> $relations */
    private function createDune(array $relations): TestResponse
    {
        return $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Dune'],
            'relations' => $relations,
        ]]]);
    }
}
