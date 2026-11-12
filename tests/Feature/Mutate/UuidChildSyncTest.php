<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingAuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UuidChildSyncTest extends PackageTestCase
{
    private const string KEPT = '0193b9e2-7a00-7000-8000-000000000001';

    private const string LEFT = '0193b9e2-7a00-7000-8000-000000000002';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app?->bind(AuthorResource::class, SyncingAuthorResource::class);
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        DB::table('authors')->insert(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token']);
        DB::table('drafts')->insert([
            ['id' => self::KEPT, 'author_id' => 1, 'title' => 'Engines'],
            ['id' => self::LEFT, 'author_id' => 1, 'title' => 'Notes'],
        ]);
    }

    #[Test]
    public function it_refuses_a_sync_of_uuid_children_that_leaves_one_out(): void
    {
        $response = $this->sync([['operation' => 'sync', 'key' => [self::KEPT]]]);

        $response->assertConflict()->assertJsonPath('conflicts', [['reason' => 'unlisted', 'path' => 'mutate.0.relations.drafts.0', 'key' => self::LEFT]]);
        $this->assertSame([self::KEPT, self::LEFT], self::drafts());
    }

    #[Test]
    public function it_syncs_uuid_children_that_the_list_accounts_for(): void
    {
        $response = $this->sync([['operation' => 'sync', 'key' => [self::KEPT]], ['operation' => 'delete', 'key' => [self::LEFT]]]);

        $response->assertOk();
        $this->assertSame([self::KEPT], self::drafts());
    }

    /** @param list<array<string, mixed>> $entries */
    private function sync(array $entries): TestResponse
    {
        return $this->postJson('/api/authors/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'relations' => ['drafts' => $entries]]]]);
    }

    /** @return list<string> */
    private static function drafts(): array
    {
        return Draft::query()
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
