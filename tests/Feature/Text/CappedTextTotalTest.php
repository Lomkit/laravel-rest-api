<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\CappedContainsPostController;
use Lomkit\Rest\Tests\Feature\Text\Stubs\CappedFullTextPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CappedTextTotalTest extends FullTextTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('capped-contains-posts', CappedContainsPostController::class)->register();
        Rest::resource('capped-full-text-posts', CappedFullTextPostController::class)->register();
        Post::query()->insert([
            ...array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Lamp '.$id, 'summary' => 'Brass lamp', 'status' => 'published', 'published_on' => '2026-01-01'], range(1, 25)),
            ...array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Vase '.$id, 'summary' => 'Hand blown glass', 'status' => 'published', 'published_on' => '2026-01-01'], range(26, 28)),
        ]);
    }

    #[Test]
    #[DataProvider('resources')]
    public function it_counts_the_matches_of_a_text_search_up_to_the_maximum_total(string $resource): void
    {
        DB::enableQueryLog();

        $response = $this->search($resource, ['text' => ['value' => 'lamp'], 'selects' => [['field' => 'id']], 'limit' => 10]);

        $response->assertOk()->assertJsonCount(10, 'data');
        $this->assertSame(['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 10, 'to' => 10, 'total' => 20, 'total_capped' => true, 'meta' => []], Arr::except($response->json(), 'data'));
        $count = str_replace(['"', '`'], '', (string) Arr::last(DB::getQueryLog())['query']);
        $this->assertStringStartsWith('select count(*) as aggregate from (select posts.id from posts where ', $count);
        $this->assertStringEndsWith(' limit 21) as capped', $count);
    }

    /** @return array<string, array{string}> */
    public static function resources(): array
    {
        return [
            'contains' => ['capped-contains-posts'],
            'full text' => ['capped-full-text-posts'],
        ];
    }
}
