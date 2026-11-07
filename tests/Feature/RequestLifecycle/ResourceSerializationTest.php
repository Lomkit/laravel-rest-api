<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Support\Str;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ConstrainedPostController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

#[WithMigration('queue')]
class ResourceSerializationTest extends PackageTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive, new Publishing];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function queuedActions(): iterable
    {
        yield 'queued' => ['queued-publish-posts', []];
        yield 'batched' => ['batched-publish-posts', ['batch' => self::BATCH]];
    }

    #[Test]
    public function it_runs_a_queued_action_on_a_resource_whose_fields_hold_closures(): void
    {
        Document::factory()->count(2)->create();

        $response = $this->postJson('/api/documents/actions/queued-archive-documents', [
            'search' => ['sorts' => [['field' => 'title', 'direction' => 'asc']]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame(2, Document::query()->where('archived', true)->count());
    }

    /** @param array<string, string> $batch */
    #[Test]
    #[DataProvider('queuedActions')]
    public function it_runs_a_queued_action_filtered_on_a_relation_whose_constraints_hold_callables(string $action, array $batch): void
    {
        Rest::resource('constrained-posts', ConstrainedPostController::class)->register();
        $author = Author::factory()->create();
        Post::factory()->count(2)->for($author)->create();
        Post::factory()->create();

        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson("/constrained-posts/actions/{$action}", [
            'search' => ['filters' => [['field' => 'author.id', 'value' => $author->id]]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, ...$batch, 'result' => null]]);
        $this->assertSame(2, Post::query()->whereBelongsTo($author)->where('status', PostStatus::Published)->count());
        $this->assertSame(0, Post::query()->whereNull('author_id')->where('status', PostStatus::Published)->count());
    }
}
