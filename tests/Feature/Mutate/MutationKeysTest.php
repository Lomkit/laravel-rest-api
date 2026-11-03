<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyAuthorController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyPostController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\DraftOnlyPostResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MutationKeysTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const string DRAFT = '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60';

    private const string UPPER_DRAFT = '019A35F0-7C2E-7A41-9B3D-5E8F1C2A4B60';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DraftOnlyPostResource::$hooked = [];
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        Rest::resource('draft-posts', DraftOnlyPostController::class)->register();
        Rest::resource('draft-only-authors', DraftOnlyAuthorController::class)->register();
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Product::factory()->create(['id' => 1, 'category_id' => 1, 'name' => 'Desk lamp', 'price' => 39]);
        Product::factory()->create(['id' => 2, 'category_id' => 1, 'name' => 'Floor lamp', 'price' => 39]);
        Author::factory()->create(['id' => 1]);
        Author::factory()
            ->trashed()
            ->create(['id' => 2]);
        Post::factory()->create(['id' => 1, 'title' => 'Annual report', 'status' => PostStatus::Published]);
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('refusedKeys')]
    public function it_refuses_a_key_on_its_key(string $uri, array $mutate, string $key, string $message): void
    {
        $response = $this->postJson($uri, ['mutate' => $mutate]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
        $trashed = Author::withTrashed()
            ->findOrFail(2)
            ->trashed();
        $this->assertSame([39, 39], $this->prices());
        $this->assertTrue($trashed);
        $this->assertSame([0, null], [Post::query()->findOrFail(1)->views, Post::query()->findOrFail(1)->author_id]);
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string, string}> */
    public static function refusedKeys(): array
    {
        return [
            'a key of the wrong type' => ['/api/products/mutate', [
                ['operation' => 'update', 'key' => 'lamp', 'attributes' => ['price' => 10]],
            ], 'mutate.0.key', 'The mutate.0.key field must be an integer.'],
            'a null key' => ['/api/products/mutate', [
                ['operation' => 'update', 'key' => null, 'attributes' => ['price' => 10]],
            ], 'mutate.0.key', 'The mutate.0.key field must be an integer.'],
            'a key list at the root' => ['/api/products/mutate', [
                ['operation' => 'update', 'key' => [1, 2], 'attributes' => ['price' => 10]],
            ], 'mutate.0.key', 'The mutate.0.key field must be an integer.'],
            'a key list in a to-one relation' => ['/api/products/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['category' => ['operation' => 'attach', 'key' => [1]]]],
            ], 'mutate.0.relations.category.key', 'The mutate.0.relations.category.key field must be an integer.'],
            'a key list item of the wrong type' => ['/api/categories/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['products' => [['operation' => 'attach', 'key' => [1, 'lamp']]]]],
            ], 'mutate.0.relations.products.0.key.1', 'The mutate.0.relations.products.0.key.1 field must be an integer.'],
            'a key list item under a camel case relation' => ['/api/categories/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['featuredProducts' => [['operation' => 'attach', 'key' => [1, 'lamp']]]]],
            ], 'mutate.0.relations.featuredProducts.0.key.1', 'The mutate.0.relations.featuredProducts.0.key.1 field must be an integer.'],
            'a key list given as an object' => ['/api/categories/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['products' => [['operation' => 'attach', 'key' => ['first' => 1]]]]],
            ], 'mutate.0.relations.products.0.key', 'The mutate.0.relations.products.0.key field must be a list.'],
            'a key that is not a UUID' => ['/api/authors/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['drafts' => [['operation' => 'update', 'key' => 'draft-1']]]],
            ], 'mutate.0.relations.drafts.0.key', 'The mutate.0.relations.drafts.0.key field must be a valid UUID.'],
            'a key that the mutate query leaves out' => ['/draft-posts/mutate', [
                ['operation' => 'update', 'key' => 1, 'attributes' => ['views' => 3]],
            ], 'mutate.0.key', 'The selected mutate.0.key is invalid.'],
            'a key that the mutate query of a related resource leaves out' => ['/draft-only-authors/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['posts' => [['operation' => 'update', 'key' => 1, 'attributes' => ['views' => 3]]]]],
            ], 'mutate.0.relations.posts.0.key', 'The selected mutate.0.relations.posts.0.key is invalid.'],
            'an attach key that the mutate query leaves out' => ['/draft-only-authors/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['posts' => [['operation' => 'attach', 'key' => 1]]]],
            ], 'mutate.0.relations.posts.0.key', 'The selected mutate.0.relations.posts.0.key is invalid.'],
            'a soft-deleted key' => ['/api/authors/mutate', [
                ['operation' => 'update', 'key' => 2, 'attributes' => ['first_name' => 'Grace']],
            ], 'mutate.0.key', 'The selected mutate.0.key is invalid.'],
            'a missing key of a key list' => ['/api/categories/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['products' => [['operation' => 'attach', 'key' => [1, 99]]]]],
            ], 'mutate.0.relations.products.0.key', 'The selected mutate.0.relations.products.0.key is invalid.'],
        ];
    }

    #[Test]
    public function it_refuses_a_key_on_create_without_checking_it(): void
    {
        $response = $this->postJson('/api/products/mutate', ['mutate' => [
            ['operation' => 'create', 'key' => 99, 'attributes' => ['name' => 'Floor lamp', 'price' => 129]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.key' => ['The mutate.0.key field is prohibited when mutate.0.operation is create.']]);
    }

    #[Test]
    public function it_refuses_a_key_list_on_create_without_reading_it(): void
    {
        config(['rest.limits.sizes.values' => 2]);

        $response = $this->postJson('/api/products/mutate', ['mutate' => [
            ['operation' => 'create', 'key' => [1, 2, 3], 'attributes' => ['name' => 'Office chair', 'price' => 149]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.key' => ['The mutate.0.key field is prohibited when mutate.0.operation is create.']]);
        $this->assertSame(2, Product::query()->count());
    }

    #[Test]
    public function it_reads_a_key_sent_as_a_string(): void
    {
        $response = $this->postJson('/api/products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => '2', 'attributes' => ['price' => 10]],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => 2]]]);
        $this->assertSame([39, 10], $this->prices());
    }

    /** @param list<string|list<string>> $keys */
    #[Test]
    #[DataProvider('caseVariants')]
    public function it_checks_each_case_of_a_uuid_key_as_it_was_sent(array $keys, string $key): void
    {
        Draft::factory()
            ->for(Author::factory()->create(['id' => 3]))
            ->create(['id' => self::DRAFT]);
        $anyCase = Draft::query()
            ->whereKey(self::UPPER_DRAFT)
            ->exists();

        if ($anyCase) {
            $this->markTestSkipped('The database finds a UUID key in any case.');
        }

        $drafts = array_map(fn (string|array $draft): array => ['operation' => 'attach', 'key' => $draft], $keys);

        $response = $this->postJson('/api/authors/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['drafts' => $drafts]],
        ]]);

        $message = "The selected {$key} is invalid.";
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
        $this->assertSame(3, Draft::query()->findOrFail(self::DRAFT)->author_id);
    }

    /** @return array<string, array{list<string|list<string>>, string}> */
    public static function caseVariants(): array
    {
        return [
            'the lowercase form first' => [[self::DRAFT, self::UPPER_DRAFT], 'mutate.0.relations.drafts.1.key'],
            'the uppercase form first' => [[[self::UPPER_DRAFT], self::DRAFT], 'mutate.0.relations.drafts.0.key'],
            'both forms in one key list' => [[[self::DRAFT, self::UPPER_DRAFT]], 'mutate.0.relations.drafts.0.key'],
        ];
    }

    /** @return array<int, mixed> */
    private function prices(): array
    {
        return Product::query()
            ->orderBy('id')
            ->pluck('price')
            ->all();
    }
}
