<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RefusingLibraryLinksPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RefusingPostLinksPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ViewOnlyPostPolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class ChildChecksTest extends PackageTestCase
{
    use SeedsLibraryRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Book::class, Loan::class, Label::class] as $model) {
            Gate::policy($model, RefusingLibraryLinksPolicy::class);
        }

        Gate::policy(Author::class, RefusingPostLinksPolicy::class);
        $this->seedLibrary();
        Author::query()->insert(['id' => 101, 'first_name' => 'Ada', 'last_name' => 'Byron', 'api_token' => 'token-1']);
        Post::query()->insert(['id' => 101, 'author_id' => 101, 'title' => 'Notes', 'status' => 'draft', 'published_on' => '2026-01-01']);
    }

    /** @param array<string, mixed> $relations */
    #[Test]
    #[DataProvider('refusedChanges')]
    public function it_refuses_a_change_of_a_child_that_the_parent_policy_refuses(string $uri, array $relations, int $key = 101): void
    {
        $rows = [self::library(), self::posts()];

        $response = $this->postJson($uri, ['mutate' => [['operation' => 'update', 'key' => $key, 'relations' => $relations]]]);

        $response->assertForbidden()->assertExactJson(['message' => 'This action is unauthorized.']);
        $this->assertSame($rows, [self::library(), self::posts()]);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2?: int}> */
    public static function refusedChanges(): array
    {
        return [
            'has one update' => ['/api/books/mutate', ['loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']]]],
            'has one detach' => ['/api/books/mutate', ['loan' => ['operation' => 'detach', 'key' => 101]]],
            'has one of many update' => ['/api/books/mutate', ['latestLoan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Ada Lovelace']]]],
            'has one of many detach' => ['/api/books/mutate', ['latestLoan' => ['operation' => 'detach', 'key' => 101]]],
            'has many update' => ['/api/authors/mutate', ['posts' => [['operation' => 'update', 'key' => 101, 'attributes' => ['views' => 7]]]]],
            'has many detach' => ['/api/authors/mutate', ['posts' => [['operation' => 'detach', 'key' => [101]]]]],
            'morph one update' => ['/api/books/mutate', ['label' => ['operation' => 'update', 'key' => 101, 'attributes' => ['text' => 'old']]]],
            'morph one detach' => ['/api/books/mutate', ['label' => ['operation' => 'detach', 'key' => 101]]],
            'morph one of many update' => ['/api/books/mutate', ['latestLabel' => ['operation' => 'update', 'key' => 101, 'attributes' => ['text' => 'old']]]],
            'morph one of many detach' => ['/api/books/mutate', ['latestLabel' => ['operation' => 'detach', 'key' => 101]]],
            'morph many update' => ['/api/books/mutate', ['labels' => [['operation' => 'update', 'key' => 101, 'attributes' => ['text' => 'old']]]]],
            'morph many detach' => ['/api/books/mutate', ['labels' => [['operation' => 'detach', 'key' => [101]]]]],
            'belongs to detach' => ['/api/loans/mutate', ['book' => ['operation' => 'detach', 'key' => 101]]],
            'belongs to update' => ['/api/loans/mutate', ['book' => ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Dune Messiah']]]],
            'has one attach' => ['/api/books/mutate', ['loan' => ['operation' => 'attach', 'key' => 101]]],
            'has one create' => ['/api/books/mutate', ['loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]], 107],
            'has one of many attach' => ['/api/books/mutate', ['latestLoan' => ['operation' => 'attach', 'key' => 103]]],
            'has one of many create' => ['/api/books/mutate', ['latestLoan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]]],
            'has many attach' => ['/api/authors/mutate', ['posts' => [['operation' => 'attach', 'key' => [101]]]]],
            'has many create' => ['/api/authors/mutate', ['posts' => [['operation' => 'create', 'attributes' => ['title' => 'Drafts', 'status' => 'draft', 'published_on' => '2026-02-01']]]]],
            'morph one attach' => ['/api/books/mutate', ['label' => ['operation' => 'attach', 'key' => 101]]],
            'morph one create' => ['/api/books/mutate', ['label' => ['operation' => 'create', 'attributes' => ['text' => 'new']]], 107],
            'morph one of many attach' => ['/api/books/mutate', ['latestLabel' => ['operation' => 'attach', 'key' => 103]]],
            'morph one of many create' => ['/api/books/mutate', ['latestLabel' => ['operation' => 'create', 'attributes' => ['text' => 'new']]]],
            'morph many attach' => ['/api/books/mutate', ['labels' => [['operation' => 'attach', 'key' => [103]]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $posts
     */
    #[Test]
    #[DataProvider('childAbilities')]
    public function it_checks_update_on_an_updated_child_and_view_on_a_detached_one(array $entry, int $status, array $posts): void
    {
        Gate::policy(Author::class, OpenPublishingPolicy::class);
        Gate::policy(Post::class, ViewOnlyPostPolicy::class);

        $response = $this->postJson('/api/authors/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['posts' => [$entry]]]]]);

        $response->assertStatus($status);
        $this->assertSame($posts, self::posts());
    }

    /** @return array<string, array{array<string, mixed>, int, list<string>}> */
    public static function childAbilities(): array
    {
        return [
            'update' => [['operation' => 'update', 'key' => 101, 'attributes' => ['views' => 7]], 403, ['Notes > 101 0']],
            'detach' => [['operation' => 'detach', 'key' => [101]], 200, ['Notes > - 0']],
            'attach' => [['operation' => 'attach', 'key' => [101]], 200, ['Notes > 101 0']],
        ];
    }

    /** @return list<string> */
    private static function posts(): array
    {
        return Post::query()
            ->orderBy('title')
            ->get()
            ->map(fn (Post $post): string => $post->getAttribute('title').' > '.($post->getAttribute('author_id') ?? '-').' '.$post->getAttribute('views'))
            ->all();
    }
}
