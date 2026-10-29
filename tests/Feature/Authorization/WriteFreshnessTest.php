<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Foundation\Application;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Orchestra\Testbench\Attributes\DefineEnvironment;
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
    #[DefineEnvironment('enableCache')]
    public function it_checks_a_second_update_against_the_new_state_with_the_cache_on(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->postJson('/api/tickets/mutate', $this->closeThenRename($jam))
            ->assertStatus(423)
            ->assertJsonPath('message', 'Closed tickets are read-only.');

        $this->assertSame($this->calls('update', $jam, $jam), $this->tickets->calls);
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

    protected function enableCache(Application $app): void
    {
        $app['config']->set('rest.authorizations.cache.enabled', true);
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
