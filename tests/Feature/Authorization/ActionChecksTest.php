<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LeadTicketController;
use PHPUnit\Framework\Attributes\Test;

class ActionChecksTest extends HelpdeskTestCase
{
    #[Test]
    public function it_refuses_an_unknown_action_to_a_user_who_cannot_list(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/actions/merge-tickets')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_answers_not_found_for_an_unknown_action_once_listing_is_allowed(): void
    {
        $this->postJson('/api/tickets/actions/merge-tickets')->assertNotFound();

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_listing_before_running_an_action(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/actions/close-tickets', ['fields' => [['name' => 'unknown', 'value' => 1]]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);

        $this->assertSame('open', $jam->fresh()?->status);
    }

    #[Test]
    public function it_runs_an_action_once_listing_is_allowed(): void
    {
        ['Printer jam' => $jam, 'Printer toner' => $toner] = $this->seedTickets();

        $this->postJson('/api/tickets/actions/close-tickets', ['search' => ['filters' => [['field' => 'team.name', 'value' => 'Billing']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 2]]);

        $this->assertSame(['closed', 'closed'], [$jam->fresh()?->status, $toner->fresh()?->status]);
        $this->assertSame([['viewAny'], ['viewAny']], [$this->tickets->calls, $this->teams->calls]);
    }

    #[Test]
    public function it_checks_listing_before_running_a_standalone_action(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/actions/refresh-tickets')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);
    }

    #[Test]
    public function it_runs_a_standalone_action_once_listing_is_allowed(): void
    {
        $this->postJson('/api/tickets/actions/refresh-tickets')->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_the_abilities_an_action_adds(): void
    {
        $this->tickets->answers['create'] = Response::deny('Tickets are imported by the helpdesk lead.');

        $this->postJson('/api/tickets/actions/import-tickets')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are imported by the helpdesk lead.']);

        $this->assertSame(['viewAny', 'create'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_listing_before_the_abilities_an_action_adds(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/actions/import-tickets')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_answers_a_user_who_cannot_list_the_same_way_for_a_known_and_an_unknown_action(): void
    {
        $this->renderDenials();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');
        $this->tickets->answers['create'] = Response::deny('Tickets cannot be created.');

        $known = $this->postJson('/api/tickets/actions/import-tickets')->assertForbidden()->json();
        $unknown = $this->postJson('/api/tickets/actions/merge-tickets')->assertForbidden()->json();

        $this->assertSame($unknown, $known);
        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_listing_then_the_resource_abilities_then_the_action_abilities(): void
    {
        Rest::resource('lead-tickets', LeadTicketController::class)->register();

        $this->postJson('/lead-tickets/actions/import-tickets')->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);

        $this->assertSame(['viewAny', 'runActions', 'create'], $this->tickets->calls);
    }

    #[Test]
    public function it_runs_an_action_without_abilities_for_a_user_who_cannot_list(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/actions/report-outage')->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);

        $this->assertSame([], $this->tickets->calls);
    }

    #[Test]
    public function it_runs_the_search_of_an_action_without_abilities_for_a_user_who_cannot_list(): void
    {
        $this->seedTickets();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/actions/watch-tickets', ['search' => ['filters' => [['field' => 'status', 'value' => 'open']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 2]]);

        $this->assertSame([], $this->tickets->calls);
    }

    #[Test]
    public function it_runs_an_action_without_abilities_without_the_abilities_the_resource_requires(): void
    {
        Rest::resource('lead-tickets', LeadTicketController::class)->register();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');
        $this->tickets->answers['runActions'] = Response::deny('Only the helpdesk lead does this.');

        $this->postJson('/lead-tickets/actions/report-outage')->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);

        $this->assertSame([], $this->tickets->calls);
    }
}
