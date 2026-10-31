<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FullTextGrammarTest extends FullTextTestCase
{
    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $lovelace = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $turing = Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

        foreach ([
            'desk' => ['Desk lamp', 'Desk lamp with a lamp shade', $lovelace, 10, false],
            'floor' => ['Floor lamp', 'Tall lamp', $turing, 200, true],
            'vase' => ['Glass vase', 'Hand blown glass', $lovelace, 300, false],
            'oil' => ['Lamp oil', 'Clear oil', $turing, 5, false],
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
    public function it_runs_the_whole_search_grammar_together_with_a_full_text_search(array $search, array $matches): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', ['text' => ['value' => 'lamp'], ...$search]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $this->ids($matches))
            ->assertJsonPath('total', count($matches));
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function searchesWithText(): array
    {
        return [
            'no other criteria' => [[], ['desk', 'floor', 'oil']],
            'an or filter' => [['filters' => [
                ['field' => 'views', 'operator' => '<', 'value' => 50],
                ['field' => 'title', 'value' => 'Glass vase', 'type' => 'or'],
            ]], ['desk', 'oil']],
            'a nested filter' => [['filters' => [['nested' => [
                ['field' => 'views', 'operator' => '>', 'value' => 100],
                ['field' => 'title', 'operator' => 'like', 'value' => 'Lamp%', 'type' => 'or'],
            ]]]], ['floor', 'oil']],
            'a relation filter' => [['filters' => [['field' => 'author.last_name', 'value' => 'Turing']]], ['floor', 'oil']],
            'a scope' => [['scopes' => [['name' => 'popular', 'parameters' => [100]]]], ['floor']],
            'an instruction' => [['instructions' => [['name' => 'highlight-posts']]], ['floor']],
            'a sort' => [['sorts' => [['field' => 'views', 'direction' => 'desc']]], ['floor', 'desk', 'oil']],
            'a relevance sort after a field sort' => [['sorts' => [['field' => 'featured'], ['field' => '@relevance']]], ['desk', 'oil', 'floor']],
            'a page' => [['limit' => 10, 'page' => 1], ['desk', 'floor', 'oil']],
        ];
    }

    #[Test]
    public function it_loads_the_includes_and_aggregates_of_the_rows_in_the_order_of_relevance(): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', [
            'text' => ['value' => 'lamp'],
            'filters' => [['field' => 'author.first_name', 'operator' => 'like', 'value' => 'A%']],
            'includes' => [['relation' => 'author', 'filters' => [['field' => 'last_name', 'value' => 'Turing']]]],
            'aggregates' => [['relation' => 'author', 'type' => 'count', 'filters' => [['field' => 'first_name', 'value' => 'Ada']]]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', $this->ids(['desk', 'floor', 'oil']))
            ->assertJsonPath('data.*.author.last_name', [null, 'Turing', 'Turing'])
            ->assertJsonPath('data.*.author_count', [1, 0, 0])
            ->assertJsonPath('total', 3);
    }

    /**
     * @param  list<string>  $posts
     * @return list<int>
     */
    private function ids(array $posts): array
    {
        return array_map(fn (string $post): int => $this->posts[$post]->id, $posts);
    }
}
