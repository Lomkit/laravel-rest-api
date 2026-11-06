<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;

class WriteFreshnessTest extends HelpdeskTestCase
{
    #[Test]
    public function it_checks_a_second_update_of_a_row_against_the_state_the_first_one_wrote(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->postJson('/api/tickets/mutate', $this->closeThenRename($jam))
            ->assertStatus(423)
            ->assertJsonPath('message', 'Closed tickets are read-only.');

        $this->assertSame($this->calls('update', $jam, $jam), $this->tickets->calls);
        $this->assertDatabaseHas('tickets', ['id' => $jam->id, 'title' => 'Printer jam', 'status' => 'open']);
    }

    #[Test]
    public function it_checks_a_second_update_against_the_new_state_when_its_resource_caches_updates(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->postJson('/api/cached-tickets/mutate', $this->closeThenRename($jam))
            ->assertStatus(423)
            ->assertJsonPath('message', 'Closed tickets are read-only.');

        $this->assertSame($this->calls('update', $jam, $jam), $this->tickets->calls);
    }

    #[Test]
    public function it_checks_a_created_row_whatever_the_store_holds_for_its_key(): void
    {
        $last = $this->seedTickets()['Laptop setup'];
        $stale = (new Ticket)->forceFill(['id' => $last->id + 1, 'team_id' => $last->team_id, 'title' => 'Printer jam', 'status' => 'open']);
        $stale->exists = true;
        $this->contextFor(null)
            ->resource(CachedTicketResource::class)
            ->authorizedTo('update', $stale);
        $this->tickets->calls = [];

        $response = $this->postJson('/api/cached-tickets/mutate', [
            'mutate' => [['operation' => 'create', 'attributes' => ['team_id' => $last->team_id, 'title' => 'Monitor flicker', 'status' => 'closed']]],
            'response' => ['selects' => [['field' => 'status']], 'gates' => ['update']],
        ]);

        $response->assertOk()->assertExactJson([
            'operations' => [['operation' => 'create', 'key' => $stale->id]],
            'records' => [['status' => 'closed', 'gates' => ['authorized_to_update' => ['allowed' => false, 'message' => 'Closed tickets are read-only.']]]],
        ]);
        $this->assertSame(['create', 'viewAny', 'create', 'update '.$stale->id], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_a_row_again_once_it_is_forgotten(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $context = $this->contextFor(null);
        $resource = $context->resource(TicketResource::class);
        $ticket = Ticket::query()->findOrFail($jam->id);

        $before = $resource->authorizedTo('update', $ticket);
        $ticket->update(['status' => 'closed']);
        $remembered = $resource->authorizedTo('update', $ticket);
        $context->authorizer()->forget(Ticket::query()->findOrFail($jam->id));
        $after = $resource->authorizedTo('update', $ticket);

        $this->assertTrue($before->allowed());
        $this->assertTrue($remembered->allowed());
        $this->assertSame([false, 'Closed tickets are read-only.', 423], [$after->allowed(), $after->message(), $after->status()]);
        $this->assertSame($this->calls('update', $jam, $jam), $this->tickets->calls);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function closeThenRename(Ticket $ticket): array
    {
        return ['mutate' => [
            ['operation' => 'update', 'key' => $ticket->id, 'attributes' => ['status' => 'closed']],
            ['operation' => 'update', 'key' => $ticket->id, 'attributes' => ['title' => 'Printer fixed']],
        ]];
    }
}
