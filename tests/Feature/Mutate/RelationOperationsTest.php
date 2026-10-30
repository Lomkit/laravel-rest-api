<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

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

class RelationOperationsTest extends PackageTestCase
{
    private const array CREATIONS = [
        'books' => ['title' => 'Emma'],
        'loans' => ['borrower' => 'Grace'],
        'labels' => ['text' => 'Featured'],
    ];

    protected function fixtures(): array
    {
        return [new Library, new Catalog];
    }

    #[Test]
    #[DataProvider('toOneRelations')]
    public function it_refuses_sync_and_toggle_on_to_one_relations(string $endpoint, string $relation, string $name, string $target, string $operation): void
    {
        $keys = [
            'book' => Book::query()->create(['title' => 'Dune'])->id,
            'loan' => Loan::query()->create(['borrower' => 'Ada'])->id,
            'label' => Label::query()->create(['text' => 'Classic'])->id,
        ];

        $response = $this->postJson('/api/'.$endpoint.'/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => self::CREATIONS[$endpoint],
            'relations' => [$relation => ['operation' => $operation, 'key' => $keys[$target]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.'.$relation.'.operation' => ['The selected mutate.0.relations.'.$name.'.operation is invalid.'],
        ]);
        $this->assertSame(['Dune'], Book::query()->pluck('title')->all());
        $this->assertNull(Loan::query()->sole()->book_id);
        $this->assertNull(Label::query()->sole()->labelable_id);
    }

    /** @return iterable<string, array{string, string, string, string, string}> */
    public static function toOneRelations(): iterable
    {
        foreach (['sync', 'toggle'] as $operation) {
            yield 'belongs to, '.$operation => ['loans', 'book', 'book', 'book', $operation];
            yield 'morph to, '.$operation => ['labels', 'labelable', 'labelable', 'book', $operation];
            yield 'has one, '.$operation => ['books', 'loan', 'loan', 'loan', $operation];
            yield 'has one of many, '.$operation => ['books', 'latestLoan', 'latest loan', 'loan', $operation];
            yield 'morph one, '.$operation => ['books', 'label', 'label', 'label', $operation];
            yield 'morph one of many, '.$operation => ['books', 'latestLabel', 'latest label', 'label', $operation];
        }
    }

    #[Test]
    #[DataProvider('pivotOperations')]
    public function it_refuses_sync_and_toggle_on_one_to_many_relations(string $operation): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $seating = Category::factory()->create(['name' => 'Seating']);
        $deskLamp = Product::factory()->create(['category_id' => $lighting->id, 'name' => 'Desk lamp']);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $seating->id,
            'relations' => ['products' => [['operation' => $operation, 'key' => $deskLamp->id]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.products.0.operation' => ['The selected mutate.0.relations.products.0.operation is invalid.'],
        ]);
        $this->assertSame($lighting->id, $deskLamp->fresh()?->category_id);
    }

    #[Test]
    #[DataProvider('pivotOperations')]
    public function it_accepts_sync_and_toggle_on_many_to_many_relations(string $operation): void
    {
        $dune = Book::query()->create(['title' => 'Dune']);
        $scienceFiction = Shelf::query()->create(['name' => 'Science fiction']);

        $response = $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $dune->id,
            'relations' => ['shelves' => [['operation' => $operation, 'key' => $scienceFiction->id]]],
        ]]]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$dune->id]]);
        $this->assertSame([$scienceFiction->id], $dune->shelves()->pluck('shelves.id')->all());
    }

    /** @return array<string, array{string}> */
    public static function pivotOperations(): array
    {
        return ['sync' => ['sync'], 'toggle' => ['toggle']];
    }
}
