<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\Test;

class ResourceOverridesTest extends HelpdeskTestCase
{
    #[Test]
    public function it_destroys_through_the_authorized_to_of_the_resource(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->deleteJson('/api/archived-tickets', ['resources' => [$jam->id]])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tickets are archived, not deleted.');

        $this->assertDatabaseHas('tickets', ['id' => $jam->id]);
        $this->assertSame([], $this->tickets->calls);
    }

    #[Test]
    public function it_destroys_through_the_policy_when_the_resource_does_not_override_it(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->deleteJson('/api/tickets', ['resources' => [$jam->id]])
            ->assertOk();

        $this->assertDatabaseMissing('tickets', ['id' => $jam->id]);
        $this->assertSame($this->calls('delete', $jam), $this->tickets->calls);
    }
}
