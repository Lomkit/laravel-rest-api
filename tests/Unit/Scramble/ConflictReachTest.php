<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Scramble\Mutate\ConflictReach;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncingTeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ConflictReachTest extends PackageTestCase
{
    #[Test]
    public function it_reaches_a_conflict_through_a_relation_that_syncs_its_children(): void
    {
        $this->assertFalse(ConflictReach::of($this->resource(TeamResource::class)));
        $this->assertTrue(ConflictReach::of($this->resource(SyncingTeamResource::class)));
    }

    #[Test]
    public function it_reaches_a_conflict_through_a_sync_one_level_down_only_while_relations_still_nest(): void
    {
        $this->app?->bind(TeamResource::class, SyncingTeamResource::class);

        $this->assertTrue(ConflictReach::of($this->resource(TicketResource::class)));

        config(['rest.limits.depths.mutations' => 1]);

        $this->assertFalse(ConflictReach::of($this->resource(TicketResource::class)));
    }

    /** @param class-string<Resource> $resource */
    private function resource(string $resource): Resource
    {
        return (new RestContext(new RestRequest, $this->app))->resource($resource);
    }
}
