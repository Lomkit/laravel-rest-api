<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\LeadingAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ListedAuthorPostController;
use Lomkit\Rest\Tests\Feature\Serialization\SeedsPublishingRows;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationSortTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPublishingRows();
        $this->createPost(4, 2, 'Compilers', PostStatus::Published, true, 20);
    }

    /**
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     */
    #[Test]
    #[DataProvider('directions')]
    public function it_sorts_on_a_field_of_a_belongs_to_relation(string $direction, array $nullsFirst, array $nullsLast): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => [
            'sorts' => [['field' => 'author.last_name', 'direction' => $direction], ['field' => 'id']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver($nullsFirst, $nullsLast));
    }

    /** @return array<string, array{string, list<int>, list<int>}> */
    public static function directions(): array
    {
        return [
            'ascending' => ['asc', [3, 4, 1, 2], [4, 1, 2, 3]],
            'descending' => ['desc', [1, 2, 4, 3], [3, 1, 2, 4]],
        ];
    }

    /** @param Closure(Builder<Author>): mixed $scope */
    #[Test]
    #[DataProvider('relatedScopes')]
    public function it_sorts_on_a_field_of_a_relation_whose_model_adds_to_its_query(Closure $scope): void
    {
        Author::addGlobalScope('extended', $scope);

        $response = $this->postJson('/api/posts/search', ['search' => [
            'sorts' => [['field' => 'author.last_name'], ['field' => 'id']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([3, 4, 1, 2], [4, 1, 2, 3]));
    }

    /** @return array<string, array{Closure(Builder<Author>): mixed}> */
    public static function relatedScopes(): array
    {
        return [
            'a column' => [fn (Builder $query): Builder => $query->withCount('posts')],
            'an order' => [fn (Builder $query): Builder => $query->orderBy('first_name')],
        ];
    }

    #[Test]
    public function it_sorts_a_soft_deleted_related_row_as_a_missing_value(): void
    {
        Author::query()
            ->whereKey(2)
            ->delete();

        $response = $this->postJson('/api/posts/search', ['search' => [
            'sorts' => [['field' => 'author.last_name'], ['field' => 'id']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([3, 4, 1, 2], [1, 2, 3, 4]));
    }

    #[Test]
    public function it_sorts_a_related_row_outside_the_related_perimeter_as_a_missing_value(): void
    {
        Rest::resource('listed-author-posts', ListedAuthorPostController::class)->register();
        Author::create(['id' => 3, 'first_name' => 'Alan', 'last_name' => 'Hidden', 'api_token' => 'token-alan', 'settings' => null]);
        $this->createPost(5, 3, 'Ciphers', PostStatus::Published, false, 1);

        $response = $this->postJson('/listed-author-posts/search', ['search' => [
            'sorts' => [['field' => 'author.first_name'], ['field' => 'id']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([3, 5, 1, 2, 4], [1, 2, 4, 3, 5]));
    }

    #[Test]
    public function it_sorts_on_a_uuid_key_of_a_related_resource(): void
    {
        Rest::resource('leading-authors', LeadingAuthorController::class)->register();
        Draft::create(['id' => 'c0000000-0000-4000-8000-000000000001', 'author_id' => 1, 'title' => 'Engines draft']);
        Draft::create(['id' => '0a000000-0000-4000-8000-000000000002', 'author_id' => 2, 'title' => 'Compilers draft']);

        $ascending = $this->postJson('/leading-authors/search', ['search' => ['sorts' => [['field' => 'draft.id']]]]);
        $descending = $this->postJson('/leading-authors/search', ['search' => ['sorts' => [['field' => 'draft.id', 'direction' => 'desc']]]]);

        $ascending->assertOk()->assertJsonPath('data.*.id', [2, 1]);
        $descending->assertOk()->assertJsonPath('data.*.id', [1, 2]);
    }

    #[Test]
    public function it_sorts_on_a_field_of_a_has_one_relation(): void
    {
        Rest::resource('leading-authors', LeadingAuthorController::class)->register();
        Author::create(['id' => 3, 'first_name' => 'Alan', 'last_name' => 'Turing', 'api_token' => 'token-alan', 'settings' => null]);

        $response = $this->postJson('/leading-authors/search', ['search' => ['sorts' => [['field' => 'leadPost.views', 'direction' => 'desc']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->byDriver([1, 2, 3], [3, 1, 2]));
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
