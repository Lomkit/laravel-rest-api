<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class QuickStartRequestsTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const array SEARCH = ['search' => ['includes' => [['relation' => 'tags']], 'limit' => 10]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeModels('App', 'Post', 'Tag', 'User');
        $this->fakeApiInstall();
        $this->runChecksInThisProcess();

        $this->artisan('rest:quick-start', ['--model' => ['Post', 'Tag'], '--install-api' => true, '--no-interaction' => true])->assertSuccessful();

        DB::table('users')->insert(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        DB::table('posts')->insert(['id' => 1, 'title' => 'Hello REST', 'body' => 'First post', 'author_id' => 1]);
        DB::table('tags')->insert(['id' => 1, 'name' => 'php']);
        DB::table('post_tag')->insert(['post_id' => 1, 'tag_id' => 1]);
    }

    #[Test]
    public function it_refuses_a_search_without_a_signed_in_user(): void
    {
        $this->postJson('/api/posts/search', self::SEARCH)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function it_answers_the_search_of_a_signed_in_user(): void
    {
        $this->actingAs($this->user(), 'sanctum');

        $response = $this->postJson('/api/posts/search', self::SEARCH);

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Hello REST')
            ->assertJsonPath('data.0.author_id', 1)
            ->assertJsonPath('data.0.tags.0.name', 'php');
    }

    #[Test]
    public function it_refuses_every_write_of_a_signed_in_user(): void
    {
        $this->actingAs($this->user(), 'sanctum');

        $this->postJson('/api/posts/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['title' => 'Typed fields', 'body' => 'Generated from the schema', 'author_id' => 1]]]])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->deleteJson('/api/posts', ['resources' => [1]])->assertForbidden();

        $this->assertSame(1, DB::table('posts')->count());
    }

    private function user(): Authenticatable
    {
        $model = 'App\Models\User';
        $user = $model::query()->findOrFail(1);
        $this->assertInstanceOf(Authenticatable::class, $user);

        return $user;
    }
}
