<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CountedTicket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\PublicTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;

class AuthorizerTest extends HelpdeskTestCase
{
    #[Test]
    public function it_inspects_each_model_under_its_collection_key(): void
    {
        $tickets = $this->seedTickets();

        $decisions = $this->authorizer()->inspectEach(new TicketResource, 'update', collect($tickets));

        $this->assertSame(
            ['Printer jam' => true, 'Password reset' => false, 'Printer toner' => true, 'Laptop setup' => false],
            array_map(fn (Decision $decision): bool => $decision->allowed, $decisions),
        );
        $this->assertSame($this->calls('update', ...array_values($tickets)), $this->tickets->calls);
    }

    #[Test]
    public function it_checks_the_whole_batch_before_throwing(): void
    {
        $tickets = $this->seedTickets();

        try {
            $this->authorizer()->authorizeEach(new TicketResource, 'update', collect($tickets), 'resources');
            $this->fail('The batch was authorized.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(['Closed tickets are read-only.', 423], [$exception->getMessage(), $exception->status()]);
        }

        $this->assertSame($this->calls('update', ...array_values($tickets)), $this->tickets->calls);
    }

    #[Test]
    public function it_throws_the_first_denial_of_a_batch(): void
    {
        $closed = $this->seedTickets()['Password reset'];
        $this->tickets->open = false;
        $resource = new TicketResource;

        $this->assertSame(
            ['Closed tickets are read-only.', 'The helpdesk is closed.'],
            [
                $this->denialOf([new Check($resource, 'update', $closed), Check::onClass($resource, 'viewAny')]),
                $this->denialOf([Check::onClass($resource, 'viewAny'), new Check($resource, 'update', $closed)]),
            ],
        );
    }

    #[Test]
    public function it_checks_a_check_repeated_in_one_batch_once(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $resource = new TicketResource;

        $decisions = $this->authorizer()->inspectAll([
            new Check($resource, 'update', Ticket::query()->findOrFail($jam->id)),
            new Check($resource, 'update', Ticket::query()->findOrFail($jam->id)),
        ]);

        $this->assertEquals([new Decision(true), new Decision(true)], $decisions);
        $this->assertSame($this->calls('update', $jam), $this->tickets->calls);
    }

    #[Test]
    public function it_checks_each_unsaved_model_on_its_own(): void
    {
        $resource = new TicketResource;

        $this->authorizer()->inspectAll([new Check($resource, 'create', new Ticket), new Check($resource, 'create', new Ticket)]);

        $this->assertSame(['create', 'create'], $this->tickets->calls);
    }

    #[Test]
    public function it_allows_a_resource_that_disables_authorizations_without_reading_the_model(): void
    {
        $ticket = $this->countedTicket(2, 'closed');

        $decision = $this->authorizer()->inspect(new Check(new PublicTicketResource, 'update', $ticket));

        $this->assertEquals(Decision::allow(), $decision);
        $this->assertSame([0, []], [$ticket->keyReads, $this->tickets->calls]);
    }

    #[Test]
    public function it_throws_the_denial_of_a_class_check_with_its_message_and_status(): void
    {
        $this->tickets->open = false;

        try {
            $this->authorizer()->authorize(Check::onClass(new TicketResource, 'viewAny'));
            $this->fail('The check was authorized.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(['The helpdesk is closed.', 423], [$exception->getMessage(), $exception->status()]);
        }
    }

    #[Test]
    public function it_reads_each_key_a_fixed_number_of_times_however_many_rows_are_forgotten(): void
    {
        $authorizer = $this->authorizer();
        $resource = new TicketResource;
        $tickets = [];

        foreach (range(1, 200) as $id) {
            $tickets[] = $written = $this->countedTicket($id, 'open');
            $tickets[] = $shared = $this->countedTicket(1000, 'open');

            $authorizer->inspect(new Check($resource, 'view', $shared));
            $authorizer->inspect(new Check($resource, 'update', $written));
            $authorizer->forget($written);
            $authorizer->forget($shared);
        }

        $this->assertSame(400, count($this->tickets->calls));
        $this->assertSame(2000, array_sum(array_map(fn (CountedTicket $ticket): int => $ticket->keyReads, $tickets)));
    }

    /** @param list<Check> $checks */
    private function denialOf(array $checks): string
    {
        try {
            $this->authorizer()->authorizeAll($checks);
        } catch (AuthorizationException $exception) {
            return $exception->getMessage();
        }

        $this->fail('The batch was authorized.');
    }

    private function countedTicket(int $id, string $status): CountedTicket
    {
        return (new CountedTicket)->newFromBuilder(['id' => $id, 'title' => 'Printer jam', 'status' => $status]);
    }

    private function authorizer(): Authorizer
    {
        return new Authorizer(Gate::forUser(null));
    }
}
