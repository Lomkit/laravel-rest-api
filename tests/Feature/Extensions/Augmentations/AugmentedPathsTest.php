<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentationLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FlagExtension;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['flag' => FlagExtension::class])]
class AugmentedPathsTest extends PackageTestCase
{
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        AugmentationLog::reset();
        Rest::resource('path-authors', PathAuthorController::class)->register();
        Rest::resource('path-posts', PathPostController::class)->register();
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1'],
            ['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-2'],
        ]);
        DB::table('posts')->insert([
            ['id' => 1, 'author_id' => 1, 'title' => 'Notes', 'status' => 'draft', 'views' => 1, 'published_on' => '2026-01-01'],
            ['id' => 2, 'author_id' => 2, 'title' => 'Compilers', 'status' => 'draft', 'views' => 2, 'published_on' => '2026-01-01'],
        ]);
        $this->captureQueries();
    }

    #[\Override]
    protected function tearDown(): void
    {
        AugmentationLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_does_not_augment_the_rows_of_an_action(): void
    {
        $this->postJson('/path-posts/actions/publish-posts', ['search' => ['includes' => [['relation' => 'author']]]])->assertOk();

        $this->assertSame([], $this->augmented());
        $this->assertSame([], AugmentationLog::$aliases);
    }

    #[Test]
    public function it_does_not_augment_an_aggregate_subquery(): void
    {
        $this->postJson('/path-authors/search', ['search' => ['aggregates' => [['relation' => 'posts', 'type' => 'count']], 'selects' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([1], array_map(static fn (array $statement): int => substr_count($statement[0], 'flag__marker'), $this->readsOf('authors')));
        $this->assertSame(['PathAuthorResource []'], AugmentationLog::$gates);
    }

    #[Test]
    public function it_does_not_augment_the_lookup_of_a_write_and_augments_the_records_read_again(): void
    {
        $this->postJson('/path-posts/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Letters']]],
            'response' => ['selects' => [['field' => 'title']]],
        ])->assertOk()->assertJsonPath('records', [['title' => 'Letters']]);

        $this->assertSame(
            ['select * from posts where (title != ?) and posts.id in (1)', 'select posts.title, posts.id, (select \'seen\') as flag__marker from posts where (title != ?) and posts.id in (1)'],
            array_column($this->readsOf('posts'), 0),
        );
    }

    #[Test]
    public function it_augments_the_rows_of_a_lazy_search_and_not_its_keys(): void
    {
        $rows = Rest::search(PathAuthorResource::class)->as(null)->payload(['selects' => [['field' => 'id']]])->lazy(10)->all();

        $this->assertCount(2, $rows);
        $this->assertSame([0, 1], array_map(static fn (array $statement): int => substr_count($statement[0], 'flag__marker'), $this->readsOf('authors')));
    }

    /** @return list<string> */
    private function augmented(): array
    {
        return array_values(array_filter(array_column($this->statements, 0), static fn (string $sql): bool => str_contains($sql, 'flag__')));
    }
}
