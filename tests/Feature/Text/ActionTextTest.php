<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionTextTest extends TextSearchTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog, new Archive];
    }

    /**
     * @param  array<string, mixed>  $text
     * @param  list<PostStatus>  $statuses
     */
    #[Test]
    #[DataProvider('texts')]
    public function it_acts_on_the_rows_that_match_the_text_of_the_search(array $text, int $impacted, array $statuses): void
    {
        $this->createPost('Desk lamp');
        $this->createPost('Glass vase');
        $this->createPost('Floor lamp');

        $response = $this->postJson('/contains-posts/actions/publish-posts', ['search' => ['text' => $text]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted]]);
        $this->assertSame($statuses, $this->statuses());
    }

    /** @return array<string, array{array<string, mixed>, int, list<PostStatus>}> */
    public static function texts(): array
    {
        $published = PostStatus::Published;
        $draft = PostStatus::Draft;

        return [
            'a word' => [['value' => 'lamp'], 2, [$published, $draft, $published]],
            'a phrase' => [['value' => 'glass vase', 'mode' => 'phrase'], 1, [$draft, $published, $draft]],
            'a blank text' => [['value' => ' '], 3, [$published, $published, $published]],
        ];
    }

    #[Test]
    public function it_refuses_a_text_on_an_action_of_a_resource_without_text_search(): void
    {
        $this->createPost('Desk lamp');

        $response = $this->postJson('/api/posts/actions/publish-posts', ['search' => ['text' => ['value' => 'lamp']]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Text search is not available on this resource.',
            'errors' => ['search.text' => ['Text search is not available on this resource.']],
        ]);
        $this->assertSame([PostStatus::Draft], $this->statuses());
    }

    /** @param list<bool> $archived */
    #[Test]
    #[DataProvider('trashedValues')]
    public function it_acts_on_the_trashed_rows_the_search_asks_for(?string $trashed, int $impacted, array $archived): void
    {
        Document::factory()->create(['title' => 'Kept']);
        Document::factory()
            ->trashed()
            ->create(['title' => 'Gone']);

        $response = $this->postJson('/api/documents/actions/archive-documents', ['search' => array_filter(['trashed' => $trashed])]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted]]);
        $this->assertSame($archived, Document::withTrashed()
            ->orderBy('id')
            ->pluck('archived')
            ->all());
    }

    /** @return array<string, array{?string, int, list<bool>}> */
    public static function trashedValues(): array
    {
        return [
            'without trashed' => [null, 1, [true, false]],
            'with trashed' => ['with', 2, [true, true]],
            'only trashed' => ['only', 1, [false, true]],
        ];
    }

    /** @return list<PostStatus> */
    private function statuses(): array
    {
        return Post::query()
            ->orderBy('id')
            ->pluck('status')
            ->all();
    }
}
