<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Search\ComparesSearchSnapshots;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\Test;

class RelevanceCharacterizationTest extends FullTextTestCase
{
    use ComparesSearchSnapshots;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-01-02 00:00:00');

        Author::factory()->create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'settings' => null]);

        $posts = [
            1 => ['Lamp', null],
            2 => ['Lamp lamp lamp', 'lamp lamp lamp lamp'],
            3 => ['Desk lamp', 'Lamp base'],
            4 => ['Glass vase', 'Hand blown glass'],
        ];

        foreach ($posts as $id => [$title, $summary]) {
            Post::factory()->create([
                'id' => $id,
                'author_id' => 1,
                'title' => $title,
                'summary' => $summary,
                'status' => PostStatus::Published,
                'featured' => true,
                'views' => 10,
                'rating' => null,
                'published_on' => '2026-01-01',
                'published_at' => null,
                'metadata' => null,
            ]);
        }
    }

    #[Test]
    public function it_keeps_the_results_and_queries_of_a_relevance_order(): void
    {
        $this->requiresFullText();

        DB::enableQueryLog();

        $response = $this->search('perimeter-posts', ['text' => ['value' => 'lamp'], 'sorts' => [['field' => '@relevance']]]);

        $response->assertOk();
        $this->assertMatchesSnapshot('relevance', $response);
    }
}
