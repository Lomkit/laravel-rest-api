<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;

class RequestDecisionsTest extends HelpdeskTestCase
{
    private const array GATED_SEARCH = ['search' => [
        'gates' => ['view'],
        'includes' => [['relation' => 'team', 'gates' => ['view']]],
    ]];

    #[Test]
    public function it_checks_view_any_once_for_a_text_search(): void
    {
        $this->seedTickets();

        $this->postJson('/api/tickets/search', ['search' => ['text' => ['value' => 'Printer']]])
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_each_distinct_gate_of_repeated_included_rows_once(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();

        $this->postJson('/api/tickets/search', self::GATED_SEARCH)
            ->assertOk()
            ->assertJsonPath('data.0.team.gates.authorized_to_view', true)
            ->assertJsonPath('data.2.team.gates.authorized_to_view', true);

        $this->assertSame(['viewAny', ...$this->calls('view', $laptop, $toner, $reset, $jam)], $this->tickets->calls);
        $this->assertSame(['viewAny', ...$this->calls('view', $laptop->team, $jam->team)], $this->teams->calls);
    }

    #[Test]
    public function it_reports_one_evaluation_per_distinct_decision_to_gate_listeners(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $evaluations = [];
        $afterCalls = 0;
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$evaluations): void {
            $evaluations[] = $event->ability.' '.$this->describe($event->arguments[0]);
        });
        Gate::after(function (?Authenticatable $user) use (&$afterCalls): void {
            $afterCalls++;
        });

        $this->postJson('/api/tickets/search', self::GATED_SEARCH)->assertOk();

        $this->assertSame([
            'viewAny Ticket',
            'viewAny Team',
            'view Ticket '.$laptop->id,
            'view Team '.$laptop->team_id,
            'view Ticket '.$toner->id,
            'view Team '.$toner->team_id,
            'view Ticket '.$reset->id,
            'view Ticket '.$jam->id,
        ], $evaluations);
        $this->assertSame(8, $afterCalls);
    }

    #[Test]
    public function it_checks_the_same_row_once_within_a_request(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $resource = $this->contextFor(null)->resource(TicketResource::class);

        $first = $resource->authorizedTo('view', Ticket::query()->findOrFail($jam->id));
        $second = $resource->authorizedTo('view', Ticket::query()->findOrFail($jam->id));

        $this->assertTrue($first->allowed());
        $this->assertTrue($second->allowed());
        $this->assertSame($this->calls('view', $jam), $this->tickets->calls);
    }

    #[Test]
    public function it_checks_again_once_the_user_changes(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $resource = new TicketResource;

        Auth::setUser(new GenericUser(['id' => TicketPolicy::LEAD]));
        $lead = $resource->authorizedTo('delete', $jam)->allowed();
        Auth::setUser(new GenericUser(['id' => 2]));
        $agent = $resource->authorizedTo('delete', $jam)->allowed();
        Auth::forgetUser();
        $guest = $resource->authorizedTo('delete', $jam)->allowed();

        $this->assertSame([true, false, false], [$lead, $agent, $guest]);
        $this->assertSame($this->calls('delete', $jam, $jam, $jam), $this->tickets->calls);
    }

    #[Test]
    public function it_allows_a_resource_that_disables_authorizations_without_calling_the_policy(): void
    {
        $laptop = $this->seedTickets()['Laptop setup'];
        $this->tickets->open = false;

        $this->postJson('/api/public-tickets/search', ['search' => ['gates' => ['update']]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $laptop->id)
            ->assertJsonPath('data.0.gates.authorized_to_update', true);

        $this->assertSame([], $this->tickets->calls);
    }

    private function describe(mixed $subject): string
    {
        if ($subject instanceof Model) {
            return class_basename($subject).' '.$subject->getKey();
        }

        return class_basename(is_string($subject) ? $subject : '');
    }
}
