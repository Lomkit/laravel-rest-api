<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FullTextRelevanceTest extends FullTextTestCase
{
    /** @var array<string, Post> */
    private array $posts = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'no summary' => ['Lamp', null],
            'many lamps' => ['Lamp lamp lamp', 'lamp lamp lamp lamp'],
            'two lamps' => ['Desk lamp', 'Lamp base'],
            'vase' => ['Glass vase', 'Hand blown glass'],
        ] as $key => [$title, $summary]) {
            $this->posts[$key] = $this->createPost($title, $summary, ['featured' => true]);
        }
    }

    /**
     * @param  list<array<string, string>>  $sorts
     * @param  list<string>  $matches
     */
    #[Test]
    #[DataProvider('relevanceOrders')]
    public function it_ranks_a_row_that_has_a_null_column_in_another_index_group(array $sorts, array $matches): void
    {
        $this->requiresFullText();

        $response = $this->search('perimeter-posts', ['text' => ['value' => 'lamp'], 'sorts' => $sorts]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids($matches));
    }

    /** @return array<string, array{list<array<string, string>>, list<string>}> */
    public static function relevanceOrders(): array
    {
        return [
            'without sorts' => [[], ['many lamps', 'two lamps', 'no summary']],
            'descending' => [[['field' => '@relevance']], ['many lamps', 'two lamps', 'no summary']],
            'ascending' => [[['field' => '@relevance', 'direction' => 'asc']], ['no summary', 'two lamps', 'many lamps']],
        ];
    }

    #[Test]
    public function it_ranks_the_matches_of_a_prefix_search(): void
    {
        $this->requiresFullText();

        $response = $this->search('perimeter-posts', ['text' => ['value' => 'lam', 'mode' => 'prefix']]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids(['many lamps', 'two lamps', 'no summary']));
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
