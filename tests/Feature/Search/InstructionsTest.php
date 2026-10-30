<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class InstructionsTest extends PackageTestCase
{
    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_passes_the_values_cast_to_the_field_types(): void
    {
        Post::factory()->create(['title' => 'Featured hit', 'featured' => true, 'views' => 300]);
        Post::factory()->create(['title' => 'Quiet post', 'featured' => false, 'views' => 20]);
        $hidden = Post::factory()->create(['title' => 'Hidden hit', 'featured' => false, 'views' => 300]);

        $response = $this->searchPosts('highlight-posts', [
            ['name' => 'featured', 'value' => '0'],
            ['name' => 'min_views', 'value' => '150'],
        ]);

        $response->assertOk()->assertExactJson($this->page([['id' => $hidden->id, 'title' => 'Hidden hit']]));
        $this->assertSame([['handle', ['featured' => false, 'min_views' => 150]]], $this->received->calls());
    }

    #[Test]
    public function it_fills_the_defaults_and_leaves_out_a_missing_field_without_default(): void
    {
        $featured = Post::factory()->create(['title' => 'Featured post', 'featured' => true]);
        Post::factory()->create(['title' => 'Plain post', 'featured' => false]);

        $response = $this->searchPosts('highlight-posts');

        $response->assertOk()->assertExactJson($this->page([['id' => $featured->id, 'title' => 'Featured post']]));
        $this->assertSame([['handle', ['featured' => true]]], $this->received->calls());
    }

    #[Test]
    public function it_counts_an_entry_without_a_value_as_missing(): void
    {
        $this->searchPosts('highlight-posts', [['name' => 'featured'], ['name' => 'min_views']])->assertOk();

        $this->assertSame([['handle', ['featured' => true]]], $this->received->calls());
    }

    #[Test]
    public function it_keeps_an_explicit_null_for_a_nullable_field(): void
    {
        $this->searchPosts('highlight-posts', [['name' => 'min_views', 'value' => null]])->assertOk();

        $this->assertSame([['handle', ['featured' => true, 'min_views' => null]]], $this->received->calls());
    }

    #[Test]
    public function it_casts_enum_and_date_values(): void
    {
        Post::factory()->create(['title' => 'Early news', 'status' => PostStatus::Published, 'published_on' => '2026-01-15']);
        Post::factory()->create(['title' => 'Pending news', 'status' => PostStatus::Review, 'published_on' => '2026-03-01']);
        $recent = Post::factory()->create(['title' => 'Recent news', 'status' => PostStatus::Published, 'published_on' => '2026-02-01']);

        $response = $this->searchPosts('in-status', [
            ['name' => 'status', 'value' => 'published'],
            ['name' => 'since', 'value' => '2026-02-01 18:00:00'],
        ]);

        $response->assertOk()->assertExactJson($this->page([['id' => $recent->id, 'title' => 'Recent news']]));
        [[$hook, $fields]] = $this->received->calls();
        $this->assertSame('handle', $hook);
        $this->assertSame(PostStatus::Published, $fields['status']);
        $this->assertInstanceOf(CarbonImmutable::class, $fields['since']);
        $this->assertSame('2026-02-01 00:00:00', $fields['since']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_does_not_require_a_field_whose_required_if_condition_is_not_met(): void
    {
        $review = Post::factory()->create(['title' => 'Pending news', 'status' => PostStatus::Review]);
        Post::factory()->create(['title' => 'Draft news', 'status' => PostStatus::Draft]);

        $response = $this->searchPosts('in-status', [['name' => 'status', 'value' => 'review']]);

        $response->assertOk()->assertExactJson($this->page([['id' => $review->id, 'title' => 'Pending news']]));
        $this->assertSame([['handle', ['status' => PostStatus::Review]]], $this->received->calls());
    }

    #[Test]
    public function it_passes_the_cast_and_defaulted_values_to_a_scout_instruction(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 129]);
        Product::factory()->for($lighting)->create(['name' => 'Arc lamp', 'price' => 300]);

        $response = $this->postJson('/api/products/search', [
            'search' => [
                'text' => ['value' => 'lamp'],
                'instructions' => [['name' => 'priced-between', 'fields' => [['name' => 'max', 'value' => '129']]]],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => $floorLamp->id, 'category_id' => $lighting->id, 'name' => 'Floor lamp', 'price' => 129],
            ['id' => $deskLamp->id, 'category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 39],
        ]);
        $this->assertSame([['handleScout', ['min' => 0, 'max' => 129]]], $this->received->calls());
    }

    #[Test]
    public function it_describes_the_instruction_fields_in_the_details(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertOk()->assertJsonPath('data.instructions', [
            [
                'name' => 'Highlight Posts',
                'uriKey' => 'highlight-posts',
                'fields' => [
                    ['name' => 'featured', 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
                    ['name' => 'min_views', 'type' => 'integer', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'integer'], 'meta' => []],
                ],
                'meta' => ['group' => 'ranking'],
            ],
            [
                'name' => 'In Status',
                'uriKey' => 'in-status',
                'fields' => [
                    ['name' => 'status', 'type' => 'enum', 'nullable' => false, 'default' => null, 'rules' => ['enum', 'required'], 'meta' => [], 'values' => ['draft', 'review', 'published']],
                    ['name' => 'since', 'type' => 'date', 'nullable' => false, 'default' => null, 'rules' => ['date', 'required_if:status,published'], 'meta' => []],
                ],
                'meta' => [],
            ],
        ]);
    }

    /** @param list<array<string, mixed>> $fields */
    private function searchPosts(string $instruction, array $fields = []): TestResponse
    {
        return $this->postJson('/api/posts/search', [
            'search' => [
                'instructions' => [['name' => $instruction, 'fields' => $fields]],
                'selects' => [['field' => 'id'], ['field' => 'title']],
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private function page(array $data): array
    {
        return [
            'current_page' => 1,
            'data' => $data,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($data),
            'total' => count($data),
            'meta' => [],
        ];
    }
}
