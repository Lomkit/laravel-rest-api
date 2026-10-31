<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FullTextIndexTest extends FullTextTestCase
{
    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'desk' => ['Desk lamp', 'Brass'],
            'vase' => ['Glass vase', 'Hand blown glass'],
            'snake' => ['snake_case', 'Naming'],
            'part' => ['ABC_123 widget', 'Spare part'],
        ] as $key => [$title, $summary]) {
            $this->posts[$key] = $this->createPost($title, $summary);
        }
    }

    #[Test]
    public function it_searches_every_searchable_field_without_an_index_declaration(): void
    {
        $response = $this->search('all-fields-full-text-posts', ['text' => ['value' => 'brass']]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->posts['desk']->id]);
    }

    /**
     * @param  array<string, string>  $text
     * @param  list<string>  $matches
     */
    #[Test]
    #[DataProvider('underscoredWords')]
    public function it_finds_a_word_that_holds_an_underscore(array $text, array $matches): void
    {
        $this->requiresFullText();

        $response = $this->search('full-text-posts', ['text' => $text]);

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $post): int => $this->posts[$post]->id, $matches));
    }

    /** @return array<string, array{array<string, string>, list<string>}> */
    public static function underscoredWords(): array
    {
        return [
            'words' => [['value' => 'ABC_123'], ['part']],
            'a phrase' => [['value' => 'abc_123 widget', 'mode' => 'phrase'], ['part']],
            'a prefix' => [['value' => 'abc_1', 'mode' => 'prefix'], ['part']],
            'a web search' => [['value' => 'snake_case -abc_123', 'mode' => 'websearch'], ['snake']],
            'another word' => [['value' => 'snake_case'], ['snake']],
        ];
    }
}
