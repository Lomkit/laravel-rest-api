<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextSearchGrammarTest extends TextSearchTestCase
{
    private Author $lovelace;

    private Author $turing;

    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->lovelace = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $this->turing = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

        foreach ([
            'desk' => ['Desk lamp', 'Brass shade', $this->lovelace, 10, false],
            'floor' => ['Floor lamp', null, $this->turing, 200, true],
            'vase' => ['Glass vase', null, $this->lovelace, 300, false],
            'oil' => ['Lamp oil', null, $this->turing, 5, false],
        ] as $key => [$title, $summary, $author, $views, $featured]) {
            $this->posts[$key] = $this->createPost($title, $summary, ['author_id' => $author->id, 'views' => $views, 'featured' => $featured]);
        }
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $matches
     */
    #[Test]
    #[DataProvider('searchesWithText')]
    public function it_runs_the_whole_search_grammar_together_with_a_text(array $search, array $matches): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'lamp'], ...$search]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', array_map(fn (string $post): int => $this->posts[$post]->id, $matches))
            ->assertJsonPath('total', count($matches));
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function searchesWithText(): array
    {
        return [
            'no other criteria' => [[], ['oil', 'floor', 'desk']],
            'an or filter' => [['filters' => [
                ['field' => 'views', 'operator' => '<', 'value' => 50],
                ['field' => 'title', 'value' => 'Glass vase', 'type' => 'or'],
            ]], ['oil', 'desk']],
            'a nested filter' => [['filters' => [['nested' => [
                ['field' => 'views', 'operator' => '>', 'value' => 100],
                ['field' => 'featured', 'value' => true, 'type' => 'or'],
            ]]]], ['floor']],
            'a relation filter' => [['filters' => [['field' => 'author.last_name', 'value' => 'Turing']]], ['oil', 'floor']],
            'a scope' => [['scopes' => [['name' => 'popular', 'parameters' => [100]]]], ['floor']],
            'an instruction' => [['instructions' => [['name' => 'highlight-posts']]], ['floor']],
            'a sort' => [['sorts' => [['field' => 'views', 'direction' => 'desc']]], ['floor', 'desk', 'oil']],
            'a page' => [['limit' => 10, 'page' => 1], ['oil', 'floor', 'desk']],
        ];
    }

    #[Test]
    public function it_loads_the_includes_and_aggregates_of_the_matching_rows(): void
    {
        $response = $this->search('contains-posts', [
            'text' => ['value' => 'floor'],
            'includes' => [['relation' => 'author']],
            'aggregates' => [['relation' => 'author', 'type' => 'count']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [$this->posts['floor']->id])
            ->assertJsonPath('data.0.author.last_name', 'Turing')
            ->assertJsonPath('data.0.author_count', 1);
    }

    #[Test]
    public function it_searches_the_text_on_the_searched_resource_only(): void
    {
        $response = $this->search('contains-authors', [
            'text' => ['value' => 'ada'],
            'filters' => [['field' => 'posts.views', 'operator' => '>', 'value' => 100]],
            'includes' => [['relation' => 'posts', 'sorts' => [['field' => 'id']]]],
            'aggregates' => [['relation' => 'posts', 'type' => 'count']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [$this->lovelace->id])
            ->assertJsonPath('data.0.posts.*.id', [$this->posts['desk']->id, $this->posts['vase']->id])
            ->assertJsonPath('data.0.posts_count', 2);
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('emptyIncludeTexts')]
    public function it_does_not_search_the_text_on_an_include_that_holds_an_empty_text(array $text): void
    {
        $response = $this->search('contains-authors', [
            'text' => ['value' => 'ada'],
            'includes' => [['relation' => 'posts', 'sorts' => [['field' => 'id']], ...$text]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [$this->lovelace->id])
            ->assertJsonPath('data.0.posts.*.id', [$this->posts['desk']->id, $this->posts['vase']->id]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function emptyIncludeTexts(): array
    {
        return [
            'a null text' => [['text' => null]],
            'an empty object' => [['text' => []]],
            'an empty string' => [['text' => '']],
        ];
    }

    #[Test]
    public function it_filters_an_include_of_a_text_search_with_the_database_grammar(): void
    {
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $seating = Category::factory()->create(['name' => 'Seating']);
        $deskLamp = Product::factory()->for($lighting)->create(['name' => 'Desk lamp', 'price' => 10]);
        Product::factory()->for($lighting)->create(['name' => 'Floor lamp', 'price' => 20]);
        Product::factory()->for($seating)->create(['name' => 'Sofa', 'price' => 30]);

        $response = $this->search('contains-categories', [
            'text' => ['value' => 'light'],
            'includes' => [['relation' => 'products', 'filters' => [['field' => 'name', 'value' => 'Desk lamp']]]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [$lighting->id])
            ->assertJsonPath('data.0.products.*.id', [$deskLamp->id]);
    }

    #[Test]
    public function it_checks_view_any_before_a_text_search_reaches_the_database(): void
    {
        Gate::before(fn (?Authenticatable $user): bool => false);
        DB::enableQueryLog();

        $response = $this->search('contains-posts', ['text' => ['value' => 'lamp']]);

        $response->assertForbidden();
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_acts_only_on_the_rows_that_match_the_text(): void
    {
        $response = $this->postJson('/contains-posts/actions/publish-posts', [
            'search' => ['text' => ['value' => 'lamp'], 'filters' => [['field' => 'views', 'operator' => '<', 'value' => 100]]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame(
            [PostStatus::Published, PostStatus::Draft, PostStatus::Draft, PostStatus::Published],
            Post::query()
                ->orderBy('id')
                ->pluck('status')
                ->all(),
        );
    }
}
