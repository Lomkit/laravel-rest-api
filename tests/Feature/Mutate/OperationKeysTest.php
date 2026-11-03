<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeyedCategoryController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RetryingCategoryController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class OperationKeysTest extends PackageTestCase
{
    private const string OUTLINE = '0b9e6c3a-2f4d-4e5b-8a7c-1d2e3f4a5b6c';

    private const string UPPER_OUTLINE = '0B9E6C3A-2F4D-4E5B-8A7C-1D2E3F4A5B6C';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[Test]
    public function it_answers_the_key_of_the_entry_as_its_field_reads_it(): void
    {
        $lighting = Category::query()->create(['name' => 'Lighting']);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => (string) $lighting->id, 'attributes' => ['name' => 'Lamps']],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => $lighting->id]]]);
    }

    #[Test]
    public function it_answers_the_uuid_keys_of_created_rows(): void
    {
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        $ada = Author::query()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada']);

        $response = $this->postJson('/api/authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['drafts' => [
                ['operation' => 'create', 'attributes' => ['id' => self::OUTLINE, 'title' => 'Outline']],
                ['operation' => 'create', 'attributes' => ['title' => 'Plan']],
            ]],
        ]]]);

        $plan = Draft::query()
            ->where('title', 'Plan')
            ->sole();
        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['drafts' => [
                ['operation' => 'create', 'key' => self::OUTLINE],
                ['operation' => 'create', 'key' => $plan->id],
            ]],
        ]]]);
        $this->assertTrue(Str::isUuid($plan->id));
    }

    #[Test]
    public function it_answers_a_uuid_key_in_the_case_that_the_entry_sent(): void
    {
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        $ada = Author::query()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada']);
        $grace = Author::query()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-grace']);
        Draft::query()->create(['id' => self::OUTLINE, 'author_id' => $grace->id, 'title' => 'Outline']);
        $anyCase = Draft::query()
            ->whereKey(self::UPPER_OUTLINE)
            ->exists();

        if (! $anyCase) {
            $this->markTestSkipped('The database finds a UUID key in its stored case only.');
        }

        $response = $this->postJson('/api/authors/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['drafts' => [['operation' => 'attach', 'key' => self::UPPER_OUTLINE]]],
        ]]]);

        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'update',
            'key' => $ada->id,
            'relations' => ['drafts' => [['operation' => 'attach', 'key' => self::UPPER_OUTLINE]]],
        ]]]);
        $this->assertSame($ada->id, Draft::query()->findOrFail(self::OUTLINE)->author_id);
    }

    #[Test]
    public function it_answers_one_entry_for_the_rows_that_a_key_list_writes(): void
    {
        $lighting = Category::query()->create(['name' => 'Lighting']);
        $seating = Category::query()->create(['name' => 'Seating']);
        $deskLamp = Product::query()->create(['category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::query()->create(['category_id' => $lighting->id, 'name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $seating->id,
            'relations' => ['products' => [[
                'operation' => 'attach',
                'key' => [$deskLamp->id, $floorLamp->id],
                'relations' => ['category' => ['operation' => 'create', 'attributes' => ['name' => 'Annex']]],
            ]]],
        ]]]);

        $annexes = Category::query()
            ->where('name', 'Annex')
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'update',
            'key' => $seating->id,
            'relations' => ['products' => [[
                'operation' => 'attach',
                'key' => [$deskLamp->id, $floorLamp->id],
                'relations' => ['category' => ['operation' => 'create', 'key' => $annexes]],
            ]]],
        ]]]);
        $this->assertCount(2, $annexes);
    }

    #[Test]
    public function it_answers_one_entry_for_each_key_that_a_custom_relation_writes_on_its_own(): void
    {
        Rest::resource('keyed-categories', KeyedCategoryController::class)->register();
        $lighting = Category::query()->create(['name' => 'Lighting']);
        $deskLamp = Product::query()->create(['category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::query()->create(['category_id' => $lighting->id, 'name' => 'Floor lamp', 'price' => 129]);

        $response = $this->postJson('/keyed-categories/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['name' => 'Seating'],
            'relations' => ['products' => [['operation' => 'update', 'key' => [$deskLamp->id, $floorLamp->id]]]],
        ]]]);

        $seating = Category::query()
            ->where('name', 'Seating')
            ->sole();
        $response->assertOk()->assertExactJson(['operations' => [[
            'operation' => 'create',
            'key' => $seating->id,
            'relations' => ['products' => [
                ['operation' => 'update', 'key' => $deskLamp->id],
                ['operation' => 'update', 'key' => $floorLamp->id],
            ]],
        ]]]);
        $this->assertSame([$seating->id, $seating->id], Product::query()
            ->orderBy('id')
            ->pluck('category_id')
            ->all());
    }

    #[Test]
    public function it_answers_only_the_entries_of_the_last_mutation_that_the_request_runs(): void
    {
        Rest::resource('retrying-categories', RetryingCategoryController::class)->register();
        $lighting = Category::query()->create(['name' => 'Lighting']);

        $response = $this->postJson('/retrying-categories/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $lighting->id, 'attributes' => ['name' => 'Lamps']],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => $lighting->id]]]);
    }
}
