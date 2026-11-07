<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Support\Str;
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
use Ramsey\Uuid\Uuid;

#[WithMigration('queue')]
class OperationGuardsTest extends PackageTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

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
        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $batch
     */
    #[Test]
    #[DataProvider('runs')]
    public function it_declares_the_action_fields_once_per_request_and_once_in_the_worker(string $action, array $body, int $impacted, int $calls, array $batch): void
    {
        $response = $this->postJson("/field-counting-posts/actions/{$action}", $body);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => $impacted, ...$batch, 'result' => null]]);
        $this->assertSame($calls, CountedPublishPosts::$calls);
    }

    /** @return array<string, array{string, array<string, mixed>, int, int, array<string, string>}> */
    public static function runs(): array
    {
        $fields = ['fields' => [['name' => 'at', 'value' => '2026-03-01 09:30:00']]];
        $search = [...$fields, 'search' => ['filters' => [['field' => 'title', 'value' => 'Annual report']]]];

        return [
            'synchronous' => ['counted-publish-posts', $search, 1, 1, []],
            'queued' => ['queued-counted-publish-posts', $search, 1, 2, []],
            'batched' => ['batched-counted-publish-posts', $search, 1, 2, ['batch' => self::BATCH]],
            'targeted' => ['targeted-counted-publish-posts', [...$fields, 'resources' => [1]], 1, 1, []],
            'standalone' => ['standalone-counted-publish-posts', $fields, 0, 1, []],
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

    /** @param array<string, string> $batch */
    #[Test]
    #[DataProvider('closureRuledActions')]
    public function it_queues_an_action_whose_own_fields_hold_closures(string $action, array $batch): void
    {
        $response = $this->postJson("/field-counting-posts/actions/{$action}", ['fields' => [['name' => 'reason', 'value' => 'spam']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, ...$batch, 'result' => null]]);
        $this->assertSame(
            [PostStatus::Published, PostStatus::Published],
            Post::query()
                ->orderBy('id')
                ->pluck('status')
                ->all(),
        );
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function closureRuledActions(): array
    {
        return [
            'queued' => ['queued-closure-ruled-publish-posts', []],
            'batched' => ['batched-closure-ruled-publish-posts', ['batch' => self::BATCH]],
        ];
    }
}
