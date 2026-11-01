<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CountedPublishPosts;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\FieldCountingPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
class OperationGuardsTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        CountedPublishPosts::$calls = 0;
        Rest::resource('field-counting-posts', FieldCountingPostController::class)->register();
        Post::factory()->create(['id' => 1, 'title' => 'Annual report', 'status' => PostStatus::Draft]);
        Post::factory()->create(['id' => 2, 'title' => 'Quarterly report', 'status' => PostStatus::Draft]);
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('runs')]
    public function it_declares_the_action_fields_once_per_request(string $action, array $body, int $impacted): void
    {
        $response = $this->postJson("/field-counting-posts/actions/{$action}", $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted]]);
        $this->assertSame(1, CountedPublishPosts::$calls);
    }

    /** @return array<string, array{string, array<string, mixed>, int}> */
    public static function runs(): array
    {
        $fields = ['fields' => [['name' => 'at', 'value' => '2026-03-01 09:30:00']]];
        $search = [...$fields, 'search' => ['filters' => [['field' => 'title', 'value' => 'Annual report']]]];

        return [
            'synchronous' => ['counted-publish-posts', $search, 1],
            'queued' => ['queued-counted-publish-posts', $search, 1],
            'batched' => ['batched-counted-publish-posts', $search, 1],
            'targeted' => ['targeted-counted-publish-posts', [...$fields, 'resources' => [1]], 1],
            'standalone' => ['standalone-counted-publish-posts', $fields, 0],
        ];
    }

    #[Test]
    public function it_parses_the_operation_once_when_the_rules_are_read_twice(): void
    {
        $request = OperateRequest::create('/field-counting-posts/actions/counted-publish-posts', 'POST', ['fields' => [['name' => 'at', 'value' => null]]])
            ->setContainer($this->app);
        $route = $this->app['router']->getRoutes()->match($request);
        $route->getController();
        $request->setRouteResolver(fn () => $route);

        $this->assertSame($request->rules(), $request->rules());
        $this->assertSame(1, CountedPublishPosts::$calls);
    }

    #[Test]
    public function it_runs_no_statement_before_refusing_an_operation(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/posts/actions/change-status', [
            'search' => ['filters' => array_fill(0, 50, ['field' => 'views', 'value' => 1])],
            'fields' => [['name' => 'status', 'value' => 'archived']],
        ]);

        $this->assertExactErrors($response, ['fields.status' => ['The selected status is invalid.']]);
        $this->assertSame([], $this->statements);
    }

    #[Test]
    #[DataProvider('closureRuledActions')]
    public function it_queues_an_action_whose_own_fields_hold_closures(string $action): void
    {
        $response = $this->postJson("/field-counting-posts/actions/{$action}", ['fields' => [['name' => 'reason', 'value' => 'spam']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame(
            [PostStatus::Published, PostStatus::Published],
            Post::query()
                ->orderBy('id')
                ->pluck('status')
                ->all(),
        );
    }

    /** @return array<string, array{string}> */
    public static function closureRuledActions(): array
    {
        return [
            'queued' => ['queued-closure-ruled-publish-posts'],
            'batched' => ['batched-closure-ruled-publish-posts'],
        ];
    }
}
