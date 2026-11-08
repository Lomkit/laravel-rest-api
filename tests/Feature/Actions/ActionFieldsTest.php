<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

#[WithMigration('queue')]
class ActionFieldsTest extends PackageTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
    }

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

    /**
     * @param  list<string>  $hooks
     * @param  array<string, string>  $batch
     */
    #[Test]
    #[DataProvider('runs')]
    public function it_passes_the_same_cast_map_to_every_run(string $action, array $hooks, array $batch): void
    {
        Post::factory()->count(2)->create();
        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);

        $response = $this->postJson("/api/posts/actions/{$action}", [
            'search' => [],
            'fields' => [['name' => 'at', 'value' => '2026-03-01 09:30:00']],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, ...$batch, 'result' => null]]);
        $this->assertSame($hooks, array_column($this->received->calls(), 0));

        foreach ($this->received->calls() as [, $fields]) {
            $this->assertSame(['at', 'notify'], array_keys($fields));
            $this->assertInstanceOf(CarbonImmutable::class, $fields['at']);
            $this->assertSame('2026-03-01 09:30:00', $fields['at']->format('Y-m-d H:i:s'));
            $this->assertTrue($fields['notify']);
        }

        $this->assertSame(2, Post::query()->where('status', PostStatus::Published)->where('published_at', '2026-03-01 09:30:00')->count());
    }

    /** @return array<string, array{string, list<string>, array<string, string>}> */
    public static function runs(): array
    {
        return [
            'synchronous' => ['publish-posts', ['handle'], []],
            'queued' => ['queued-publish-posts', ['handle'], []],
            'batched' => ['batched-publish-posts', ['withBatch', 'handle'], ['batch' => self::BATCH]],
        ];
    }

    #[Test]
    public function it_keeps_an_explicit_null_for_a_nullable_field(): void
    {
        Post::factory()->create();

        $this->postJson('/api/posts/actions/publish-posts', ['search' => [], 'fields' => [['name' => 'at', 'value' => null]]])->assertOk();

        $this->assertSame([['handle', ['at' => null, 'notify' => true]]], $this->received->calls());
    }

    #[Test]
    public function it_counts_an_entry_without_a_value_as_missing(): void
    {
        Post::factory()->create();

        $this->postJson('/api/posts/actions/publish-posts', ['search' => [], 'fields' => [['name' => 'at'], ['name' => 'notify']]])->assertOk();

        $this->assertSame([['handle', ['notify' => true]]], $this->received->calls());
    }

    #[Test]
    public function it_prefers_a_given_value_over_the_default(): void
    {
        Post::factory()->create();

        $this->postJson('/api/posts/actions/publish-posts', ['search' => [], 'fields' => [['name' => 'notify', 'value' => '0']]])->assertOk();

        $this->assertSame([['handle', ['notify' => false]]], $this->received->calls());
    }

    #[Test]
    public function it_describes_the_action_fields_in_the_details(): void
    {
        $response = $this->getJson('/api/posts?with=actions.fields,actions.meta,actions.authorized');

        $response->assertOk()->assertJsonPath('data.actions.0', [
            'name' => 'Publish Posts',
            'uriKey' => 'publish-posts',
            'target' => 'search',
            'fields' => [
                ['name' => 'at', 'label' => null, 'type' => 'datetime', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'date'], 'meta' => []],
                ['name' => 'notify', 'label' => null, 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
            ],
            'meta' => ['color' => '#FFFFFF'],
            'destructive' => false,
            'confirm' => null,
            'confirmation' => false,
            'authorized' => true,
        ]);
    }

    #[Test]
    public function it_casts_an_enum_value(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Draft]);

        $this->postJson('/api/posts/actions/change-status', ['search' => [], 'fields' => [['name' => 'status', 'value' => 'review']]])->assertOk();

        $this->assertSame([['handle', ['status' => PostStatus::Review]]], $this->received->calls());
        $this->assertSame(PostStatus::Review, $post->fresh()?->status);
    }
}
