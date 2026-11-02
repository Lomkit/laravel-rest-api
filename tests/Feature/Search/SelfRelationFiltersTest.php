<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ThreadedCommentController;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Comment;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SelfRelationFiltersTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    /** @return array<string, array{string, string, string}> */
    public static function selfFilters(): array
    {
        return [
            'the children' => ['replies.body', 'Reply to A', 'Root A'],
            'the parent' => ['parent.body', 'Root B', 'Reply to B'],
        ];
    }

    #[Test]
    #[DataProvider('selfFilters')]
    public function it_filters_through_a_relation_to_the_same_table(string $field, string $value, string $match): void
    {
        Rest::resource('threaded-comments', ThreadedCommentController::class)->register();
        $story = Story::create(['desk_id' => Desk::create(['name' => 'City'])->id, 'headline' => 'Bridge reopens']);
        $rootA = Comment::create(['story_id' => $story->id, 'body' => 'Root A']);
        $rootB = Comment::create(['story_id' => $story->id, 'body' => 'Root B']);
        $comments = [
            'Root A' => $rootA,
            'Root B' => $rootB,
            'Reply to A' => Comment::create(['story_id' => $story->id, 'parent_id' => $rootA->id, 'body' => 'Reply to A']),
            'Reply to B' => Comment::create(['story_id' => $story->id, 'parent_id' => $rootB->id, 'body' => 'Reply to B']),
        ];

        $response = $this->postJson('/threaded-comments/search', ['search' => ['filters' => [['field' => $field, 'value' => $value]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$comments[$match]->id]);
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function selfGroups(): array
    {
        return [
            'the replies of a comment' => [['relation' => 'replies', 'nested' => [['field' => 'body', 'value' => 'Reply to A']]], ['Root A']],
            'no parent' => [['relation' => 'parent', 'exists' => false], ['Root A', 'Root B']],
        ];
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<string>  $bodies
     */
    #[Test]
    #[DataProvider('selfGroups')]
    public function it_filters_through_a_relation_group_on_the_same_table(array $filter, array $bodies): void
    {
        Rest::resource('threaded-comments', ThreadedCommentController::class)->register();
        Desk::create(['id' => 1, 'name' => 'City']);
        Story::create(['id' => 1, 'desk_id' => 1, 'headline' => 'Bridge reopens']);
        Comment::create(['id' => 1, 'story_id' => 1, 'body' => 'Root A']);
        Comment::create(['id' => 2, 'story_id' => 1, 'body' => 'Root B']);
        Comment::create(['id' => 3, 'story_id' => 1, 'parent_id' => 1, 'body' => 'Reply to A']);
        Comment::create(['id' => 4, 'story_id' => 1, 'parent_id' => 2, 'body' => 'Reply to B']);

        $response = $this->postJson('/threaded-comments/search', ['search' => ['filters' => [$filter], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.body', $bodies);
    }
}
