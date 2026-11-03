<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Serialization\SeedsPublishingRows;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateSortTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     */
    #[Test]
    #[DataProvider('aggregates')]
    public function it_sorts_on_the_key_of_an_aggregate(array $aggregate, string $key, string $direction, array $nullsFirst, array $nullsLast): void
    {
        $this->seedPublishingRows();
        $this->createPost(4, 2, 'Compilers', PostStatus::Published, true, 20);
        Author::create(['id' => 3, 'first_name' => 'Alan', 'last_name' => 'Turing', 'api_token' => 'token-alan', 'settings' => null]);

        $response = $this->postJson('/api/authors/search', ['search' => [
            'aggregates' => [$aggregate],
            'sorts' => [['field' => $key, 'direction' => $direction], ['field' => 'first_name']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver($nullsFirst, $nullsLast));
    }

    /** @return array<string, array{array<string, mixed>, string, string, list<int>, list<int>}> */
    public static function aggregates(): array
    {
        return [
            'a count' => [['relation' => 'posts', 'type' => 'count'], 'posts_count', 'desc', [1, 2, 3], [1, 2, 3]],
            'a maximum under an alias' => [['relation' => 'posts', 'type' => 'max', 'field' => 'views', 'alias' => 'top'], 'top', 'asc', [3, 2, 1], [2, 1, 3]],
            'a count with a filter' => [['relation' => 'posts', 'type' => 'count', 'alias' => 'draft_posts', 'filters' => [['field' => 'status', 'value' => 'draft']]], 'draft_posts', 'desc', [1, 3, 2], [1, 3, 2]],
            'an existence' => [['relation' => 'tags', 'type' => 'exists'], 'tags_exists', 'desc', [1, 3, 2], [1, 3, 2]],
            'an existence with a filter' => [['relation' => 'posts', 'type' => 'exists', 'alias' => 'has_draft', 'filters' => [['field' => 'status', 'value' => 'draft']]], 'has_draft', 'asc', [3, 2, 1], [3, 2, 1]],
        ];
    }

    /** @param Closure(Builder<Post>): mixed $scope */
    #[Test]
    #[DataProvider('relatedScopes')]
    public function it_sorts_on_the_key_of_an_aggregate_whose_related_model_adds_to_its_query(Closure $scope): void
    {
        $this->seedPublishingRows();
        $this->createPost(4, 2, 'Compilers', PostStatus::Published, true, 20);
        Author::create(['id' => 3, 'first_name' => 'Alan', 'last_name' => 'Turing', 'api_token' => 'token-alan', 'settings' => null]);
        Post::addGlobalScope('extended', $scope);

        $response = $this->postJson('/api/authors/search', ['search' => [
            'aggregates' => [['relation' => 'posts', 'type' => 'max', 'field' => 'views', 'alias' => 'top']],
            'sorts' => [['field' => 'top'], ['field' => 'first_name']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([3, 2, 1], [2, 1, 3]));
    }

    /** @return array<string, array{Closure(Builder<Post>): mixed}> */
    public static function relatedScopes(): array
    {
        return [
            'a column' => [fn (Builder $query): Builder => $query->withCount('author')],
            'an order' => [fn (Builder $query): Builder => $query->orderBy('title')],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('otherLevels')]
    public function it_refuses_the_key_of_an_aggregate_that_its_level_does_not_request(array $search, array $errors): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => $search]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function otherLevels(): array
    {
        return [
            'no aggregate' => [['sorts' => [['field' => 'posts_count']]], ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']]],
            'an aggregate of an include' => [
                ['includes' => [['relation' => 'posts', 'aggregates' => [['relation' => 'author', 'type' => 'count']]]], 'sorts' => [['field' => 'author_count']]],
                ['search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.']],
            ],
            'an aggregate of the parent' => [
                ['aggregates' => [['relation' => 'posts', 'type' => 'count']], 'includes' => [['relation' => 'posts', 'sorts' => [['field' => 'posts_count']]]]],
                ['search.includes.0.sorts.0.field' => ['The selected search.includes.0.sorts.0.field is invalid.']],
            ],
        ];
    }

    #[Test]
    public function it_lists_the_errors_of_the_aggregates_before_those_of_the_sorts(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => [
            'sorts' => [['field' => 'nickname']],
            'aggregates' => [['relation' => 'editors', 'type' => 'count']],
        ]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.aggregates.0.relation field is not a valid or allowed relation for this resource. (and 1 more error)',
            'errors' => [
                'search.aggregates.0.relation' => ['The search.aggregates.0.relation field is not a valid or allowed relation for this resource.'],
                'search.sorts.0.field' => ['The selected search.sorts.0.field is invalid.'],
            ],
        ]);
        $this->assertSame(['search.aggregates.0.relation', 'search.sorts.0.field'], array_keys($response->json('errors')));
    }

    #[Test]
    public function it_lists_the_errors_of_the_aggregates_before_those_of_the_selects(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => [
            'selects' => [['field' => 'nickname']],
            'aggregates' => [['relation' => 'editors', 'type' => 'count']],
        ]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.aggregates.0.relation field is not a valid or allowed relation for this resource. (and 1 more error)',
            'errors' => [
                'search.aggregates.0.relation' => ['The search.aggregates.0.relation field is not a valid or allowed relation for this resource.'],
                'search.selects.0.field' => ['The selected search.selects.0.field is invalid.'],
            ],
        ]);
        $this->assertSame(['search.aggregates.0.relation', 'search.selects.0.field'], array_keys($response->json('errors')));
    }

    /**
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     * @return list<int>
     */
    private function byDriver(array $nullsFirst, array $nullsLast): array
    {
        return DB::connection()->getDriverName() === 'pgsql' ? $nullsLast : $nullsFirst;
    }
}
