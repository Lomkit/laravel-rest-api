<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\NewsDeskController;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Comment;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Reporter;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Summary;

trait SeedsNewsroomRows
{
    private function seedNewsroomRows(): void
    {
        Rest::resource('news-desks', NewsDeskController::class)->register();

        foreach ([[1, 'City'], [2, 'Sport'], [3, 'Arts']] as [$id, $name]) {
            Desk::create(['id' => $id, 'name' => $name]);
        }

        Reporter::create(['id' => 1, 'name' => 'Zed']);
        Reporter::create(['id' => 2, 'name' => 'Amy']);

        foreach ([[1, 1, 1, 'Bridge'], [2, 1, 2, 'Tunnel'], [3, 2, null, 'Final'], [4, 2, 1, 'Derby']] as [$id, $desk, $reporter, $headline]) {
            Story::create(['id' => $id, 'desk_id' => $desk, 'reporter_id' => $reporter, 'headline' => $headline]);
        }

        Summary::create(['id' => 1, 'story_id' => 1, 'body' => 'Mild']);
        Summary::create(['id' => 2, 'story_id' => 3, 'body' => 'Big']);

        foreach ([[1, 1, null, 'urgent'], [2, 2, null, 'urgent'], [3, 2, 2, 'urgent'], [4, 4, 1, 'calm']] as [$id, $story, $parent, $body]) {
            Comment::create(['id' => $id, 'story_id' => $story, 'parent_id' => $parent, 'body' => $body]);
        }
    }
}
