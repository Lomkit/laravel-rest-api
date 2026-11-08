<?php

namespace Lomkit\Rest\Tests\Feature\Events;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Events\ResourceRestored;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\AtomicCloseEventTickets;
use Lomkit\Rest\Tests\Feature\Events\Stubs\CloseEventTickets;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventTicketController;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventTicketResource;
use Lomkit\Rest\Tests\Feature\Events\Stubs\QueuedCloseEventTickets;
use Lomkit\Rest\Tests\Feature\Events\Stubs\TransactionlessCloseEventTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class WriteEventsTest extends HelpdeskTestCase
{
    private const array OPEN = ['filters' => [['field' => 'status', 'value' => 'open']]];

    private const array EVENTS = [ResourceMutated::class, ResourceDestroyed::class, ResourceRestored::class, ResourceForceDestroyed::class, ActionOperated::class];

    /** @var array<string, int> */
    private array $ids = [];

    /** @var list<object> */
    private array $events = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EventLog::reset();
        Rest::resource('event-tickets', EventTicketController::class)->register();
        $this->ids = array_map(fn (Ticket $ticket): int => $ticket->getKey(), $this->seedTickets());
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
        EventLog::listen();
        Event::listen(ResourceMutated::class, function (ResourceMutated $event): void {
            $this->events[] = $event;
            EventLog::record('ResourceMutated '.json_encode($event->operations));
        });
        Event::listen(ResourceDestroyed::class, function (ResourceDestroyed $event): void {
            $this->events[] = $event;
            EventLog::record('ResourceDestroyed '.implode(',', $event->models->modelKeys()));
        });
        Event::listen(ActionOperated::class, function (ActionOperated $event): void {
            $this->events[] = $event;
            EventLog::record("ActionOperated {$event->action->uriKey()} {$event->impacted}");
        });
    }

    #[Test]
    public function it_dispatches_resource_mutated_with_the_operations_once_the_mutation_is_committed(): void
    {
        $response = $this->postJson('/event-tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->ids['Printer toner'], 'attributes' => ['title' => 'Toner low']],
        ], 'response' => []]);

        $response->assertOk()->assertJsonPath('operations', [['operation' => 'update', 'key' => $this->ids['Printer toner']]]);
        $this->assertSame([
            'sql select tickets',
            'begin level 2',
            'mutatingAll update',
            'sql update tickets',
            'mutatedAll [{"operation":"update","key":'.$this->ids['Printer toner'].'}]',
            'commit level 1',
            'ResourceMutated [{"operation":"update","key":'.$this->ids['Printer toner'].'}]',
            'sql select tickets',
        ], EventLog::$lines);
        $this->assertCount(1, $this->events);
        $this->assertInstanceOf(ResourceMutated::class, $this->events[0]);
        $this->assertInstanceOf(EventTicketResource::class, $this->events[0]->resource);
    }

    #[Test]
    public function it_dispatches_resource_destroyed_with_the_deleted_models_once_the_deletion_is_committed(): void
    {
        $response = $this->deleteJson('/event-tickets', ['resources' => [$this->ids['Laptop setup'], $this->ids['Password reset']]]);

        $response->assertOk();
        $this->assertSame([
            "destroyedAll {$this->ids['Laptop setup']},{$this->ids['Password reset']}",
            'commit level 1',
            "ResourceDestroyed {$this->ids['Laptop setup']},{$this->ids['Password reset']}",
        ], array_slice(EventLog::$lines, -3));
        $this->assertCount(1, $this->events);
        $this->assertInstanceOf(ResourceDestroyed::class, $this->events[0]);
        $this->assertInstanceOf(EventTicketResource::class, $this->events[0]->resource);
        $this->assertFalse($this->events[0]->models[0]->exists);
    }

    /** @return array<string, array{string, class-string, list<string>}> */
    public static function operatedActions(): array
    {
        return [
            'one transaction per chunk' => ['close-event-tickets', CloseEventTickets::class, ['commit level 1', 'sql select tickets', 'operated close-event-tickets 2', 'ActionOperated close-event-tickets 2', 'respond 2']],
            'one transaction for the whole run' => ['atomic-close-event-tickets', AtomicCloseEventTickets::class, ['sql select tickets', 'operated atomic-close-event-tickets 2', 'commit level 1', 'ActionOperated atomic-close-event-tickets 2', 'respond 2']],
            'without transaction' => ['transactionless-close-event-tickets', TransactionlessCloseEventTickets::class, ['sql update tickets', 'sql select tickets', 'operated transactionless-close-event-tickets 2', 'ActionOperated transactionless-close-event-tickets 2', 'respond 2']],
        ];
    }

    /**
     * @param  class-string  $class
     * @param  list<string>  $tail
     */
    #[Test]
    #[DataProvider('operatedActions')]
    public function it_dispatches_action_operated_once_the_last_chunk_is_written(string $action, string $class, array $tail): void
    {
        $response = $this->postJson("/event-tickets/actions/{$action}", ['search' => self::OPEN]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame($tail, array_slice(EventLog::$lines, -5));
        $this->assertCount(1, $this->events);
        $event = $this->events[0];
        $this->assertInstanceOf(ActionOperated::class, $event);
        $this->assertInstanceOf(EventTicketResource::class, $event->resource);
        $this->assertInstanceOf($class, $event->action);
        $this->assertSame(EventLog::$payloads[1], $event->fields);
        $this->assertSame(2, $event->impacted);
        $this->assertNull($event->batch);
    }

    #[Test]
    public function it_dispatches_action_operated_in_the_request_once_the_jobs_are_queued(): void
    {
        Queue::fake();

        $response = $this->postJson('/event-tickets/actions/queued-close-event-tickets', ['search' => self::OPEN]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame(['operated queued-close-event-tickets 2', 'ActionOperated queued-close-event-tickets 2', 'respond 2'], array_slice(EventLog::$lines, -3));
        $this->assertCount(1, $this->events);
        $this->assertInstanceOf(QueuedCloseEventTickets::class, $this->events[0]->action);
        Queue::assertPushed(QueuedAction::class, 2);
    }

    /** @return array<string, array{string, string, string}> */
    public static function failedWrites(): array
    {
        return [
            'mutatedAll throws' => ['mutatedAll', 'POST', '/event-tickets/mutate'],
            'destroyedAll throws' => ['destroyedAll', 'DELETE', '/event-tickets'],
            'operated throws in the transaction of a whole action' => ['operated', 'POST', '/event-tickets/actions/atomic-close-event-tickets'],
            'operated throws after the chunks are committed' => ['operated', 'POST', '/event-tickets/actions/close-event-tickets'],
        ];
    }

    #[Test]
    #[DataProvider('failedWrites')]
    public function it_dispatches_no_event_when_the_write_fails(string $failure, string $method, string $uri): void
    {
        Event::fake(self::EVENTS);
        EventLog::$failure = $failure;
        $key = $this->ids['Printer jam'];

        $response = $this->json($method, $uri, match ($failure) {
            'mutatedAll' => ['mutate' => [['operation' => 'update', 'key' => $key, 'attributes' => ['title' => 'Paper jam']]]],
            'destroyedAll' => ['resources' => [$key]],
            default => ['search' => self::OPEN],
        });

        $response->assertInternalServerError();
        $this->assertSame([], $this->events);

        foreach (self::EVENTS as $event) {
            Event::assertNotDispatched($event);
        }
    }

    #[Test]
    public function it_dispatches_no_event_for_a_read(): void
    {
        Event::fake(self::EVENTS);

        $this->getJson('/event-tickets')->assertOk();
        $this->postJson('/event-tickets/search', ['search' => self::OPEN])->assertOk();

        foreach (self::EVENTS as $event) {
            Event::assertNotDispatched($event);
        }
    }
}
