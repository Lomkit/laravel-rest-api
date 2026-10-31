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
}
