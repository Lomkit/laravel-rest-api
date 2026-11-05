<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class UnwrittenRowDecisionsTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::query()->create(['id' => 1, 'name' => 'Billing']);
        Team::query()->create(['id' => 2, 'name' => 'Support']);
        Ticket::query()->create(['id' => 1, 'team_id' => 2, 'title' => 'Printer jam', 'status' => 'open']);
        Ticket::query()->create(['id' => 2, 'team_id' => 2, 'title' => 'Password reset', 'status' => 'open']);
        Tag::query()->create(['id' => 1, 'name' => 'Hardware']);
        Tag::query()->create(['id' => 2, 'name' => 'Office']);
    }

    #[Test]
    public function it_checks_a_row_that_two_entries_attach_without_writing_it_once(): void
    {
        $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['team' => ['operation' => 'attach', 'key' => 1]]],
            ['operation' => 'update', 'key' => 2, 'relations' => ['team' => ['operation' => 'attach', 'key' => 1]]],
        ]])->assertOk();

        $this->assertSame(['view 1'], $this->teams->calls);
        $this->assertSame(['update 1', 'update 2'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_each_tag_once_across_the_operations_of_an_entry(): void
    {
        $this->postJson('/api/tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'attributes' => ['title' => 'Printer fixed'],
            'relations' => ['tags' => [
                ['operation' => 'attach', 'key' => [1, 2]],
                ['operation' => 'detach', 'key' => [1]],
                ['operation' => 'sync', 'key' => [1, 2]],
                ['operation' => 'toggle', 'key' => [1]],
            ]],
        ]]])->assertOk();

        $this->assertSame(['view 1', 'view 2'], $this->tags->calls);
        $this->assertSame(['update 1', 'attachTag 1 1', 'attachTag 1 2', 'detachTag 1 1', 'attachTag 1 1', 'detachTag 1 1'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_a_parent_again_once_its_relations_change(): void
    {
        $this->postJson('/api/teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => 1]]]],
            ['operation' => 'update', 'key' => 1, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => 2]]]],
        ]])->assertOk();

        $this->assertSame(['update 1', 'update 1'], $this->teams->calls);
    }
}
