<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class NestedLinksTest extends PackageTestCase
{
    use AssertsExactErrors;
    use SeedsLibraryRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLibrary();
        Category::query()->insert([['id' => 101, 'name' => 'Seating'], ['id' => 102, 'name' => 'Lighting']]);
        Product::query()->insert([['id' => 101, 'category_id' => 101, 'name' => 'Chair', 'price' => 10]]);
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('nestedDetaches')]
    public function it_checks_the_entries_nested_under_a_relation_entry(string $uri, array $entry, string $key): void
    {
        $rows = [self::library(), self::products()];

        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame($rows, [self::library(), self::products()]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function nestedDetaches(): array
    {
        return [
            'under a belongs to attach' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 103, 'relations' => ['book' => ['operation' => 'attach', 'key' => 101, 'relations' => ['labels' => [['operation' => 'detach', 'key' => [102]]]]]]], 'mutate.0.relations.book.relations.labels.0.key'],
            'under a has one update' => ['/api/books/mutate', ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'detach', 'key' => 102]]]]], 'mutate.0.relations.loan.relations.book.key'],
            'under a belongs to many attach' => ['/api/categories/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => [101], 'relations' => ['category' => ['operation' => 'detach', 'key' => 102]]]]]], 'mutate.0.relations.featuredProducts.0.relations.category.key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('ownersOfChildren')]
    public function it_keeps_the_parent_of_a_child_whose_entry_names_another_owner(array $entry, string $table, array $rows): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [$entry]]);

        $response->assertOk();
        $this->assertSame($rows, self::library()[$table]);
    }

    /** @return array<string, array{array<string, mixed>, string, list<string>}> */
    public static function ownersOfChildren(): array
    {
        return [
            'has one, an attach' => [['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'update', 'key' => 102, 'attributes' => ['borrower' => 'Grace H'], 'relations' => ['book' => ['operation' => 'attach', 'key' => 101]]]]], 'loans', ['Ada > Dune', 'Alan > -', 'Grace H > Emma', 'Hidden > Ulysses']],
            'has one, a detach' => [['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada L'], 'relations' => ['book' => ['operation' => 'detach', 'key' => 101]]]]], 'loans', ['Ada L > Dune', 'Alan > -', 'Grace > Emma', 'Hidden > Ulysses']],
            'morph many, an attach' => [['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'update', 'key' => [101], 'attributes' => ['text' => 'classical'], 'relations' => ['labelable' => ['operation' => 'attach', 'type' => Shelf::class, 'key' => 101]]]]]], 'labels', ['classical > Book Dune', 'loose > - -', 'modern > Book Emma', 'shelved > Shelf Classics']],
        ];
    }

    /** @return list<string> */
    private static function products(): array
    {
        return Product::query()
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): string => $product->getAttribute('name').' > '.self::named($product->getRelationValue('category')))
            ->all();
    }
}
