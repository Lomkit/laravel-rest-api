<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Support\Str;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ConfigurableActionPostController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\ConfigurableInstructionPostController;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\RuledScopePostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

#[WithMigration('queue')]
class QueuedActionDeclarationsTest extends PackageTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
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

    /** @param array<string, string> $batch */
    #[Test]
    #[DataProvider('queuedActions')]
    public function it_runs_a_queued_action_declared_next_to_an_action_whose_fields_hold_closures(string $action, array $batch): void
    {
        Rest::resource('configurable-action-posts', ConfigurableActionPostController::class)->register();
        Post::factory()->count(2)->create();

        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson("/configurable-action-posts/actions/{$action}");

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, ...$batch, 'result' => null]]);
        $this->assertSame(2, Post::query()->where('status', PostStatus::Published)->count());
    }

    /** @param array<string, string> $batch */
    #[Test]
    #[DataProvider('queuedActions')]
    public function it_runs_a_queued_action_searched_through_an_instruction_whose_fields_hold_closures(string $action, array $batch): void
    {
        Rest::resource('configurable-instruction-posts', ConfigurableInstructionPostController::class)->register();
        Post::factory()->count(2)->create(['featured' => true]);
        Post::factory()->create(['featured' => false]);

        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson("/configurable-instruction-posts/actions/{$action}", [
            'search' => ['instructions' => [['name' => 'configurable', 'fields' => [['name' => 'featured', 'value' => true]]]]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, ...$batch, 'result' => null]]);
        $this->assertSame(2, Post::query()->where('featured', true)->where('status', PostStatus::Published)->count());
        $this->assertSame(0, Post::query()->where('featured', false)->where('status', PostStatus::Published)->count());
    }

    /** @param array<string, string> $batch */
    #[Test]
    #[DataProvider('queuedActions')]
    public function it_runs_a_queued_action_searched_through_a_scope_whose_fields_hold_closures(string $action, array $batch): void
    {
        Rest::resource('ruled-scope-posts', RuledScopePostController::class)->register();
        Post::factory()->count(2)->create(['views' => 250]);
        Post::factory()->create(['views' => 40]);

        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson("/ruled-scope-posts/actions/{$action}", [
            'search' => ['scopes' => [['name' => 'popular', 'parameters' => [100]]]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, ...$batch, 'result' => null]]);
        $this->assertSame(2, Post::query()->where('views', 250)->where('status', PostStatus::Published)->count());
        $this->assertSame(0, Post::query()->where('views', 40)->where('status', PostStatus::Published)->count());
    }
}
