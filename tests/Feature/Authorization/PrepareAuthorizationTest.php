<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\DecisionStore;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreloadedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReviewTickets;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TeamReadingTicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;

class PrepareAuthorizationTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ReviewTickets::reset();
        Rest::resource('prepared-tickets', PreparedTicketController::class)->register();
        Rest::resource('preloaded-tickets', PreloadedTicketController::class)->register();
    }

    #[Test]
    public function it_prepares_the_gates_of_a_page_once_per_ability_before_the_policy_checks_each_row(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $rows = [$jam, $reset, $toner, $laptop];

        $this->postJson('/prepared-tickets/search', ['search' => ['gates' => ['update', 'delete'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys(...$rows).' for guest',
            'prepare delete '.$this->keys(...$rows).' for guest',
            ...$this->rowCalls(['update', 'delete'], ...$rows),
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_prepares_for_the_user_the_policies_check(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->postJson('/prepared-tickets/search', ['search' => ['filters' => [['field' => 'id', 'value' => $jam->id]], 'gates' => ['update']]])
            ->assertOk();

        $this->assertSame(['viewAny', 'prepare update '.$jam->id.' for '.TicketPolicy::LEAD, 'update '.$jam->id], $this->tickets->calls);
    }

    #[Test]
    public function it_prepares_each_included_resource_with_its_own_rows(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        [$billing, $support] = [$jam->team_id, $reset->team_id];

        $this->postJson('/prepared-tickets/search', ['search' => [
            'gates' => ['update'],
            'includes' => [['relation' => 'team', 'gates' => ['view', 'update']]],
            'sorts' => [['field' => 'id']],
        ]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys($jam, $reset, $toner, $laptop).' for guest',
            ...$this->rowCalls(['update'], $jam, $reset, $toner, $laptop),
        ], $this->tickets->calls);
        $this->assertSame([
            'viewAny',
            'prepare view '.$billing.','.$support,
            'prepare update '.$billing.','.$support,
            'view '.$billing,
            'update '.$billing,
            'view '.$support,
            'update '.$support,
        ], $this->teams->calls);
    }

    #[Test]
    public function it_prepares_the_rows_of_a_destroy_before_checking_them(): void
    {
        ['Password reset' => $reset, 'Laptop setup' => $laptop] = $this->seedTickets();

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->deleteJson('/prepared-tickets', ['resources' => [$laptop->id, $reset->id]])
            ->assertOk();

        $this->assertSame(['viewAny', 'prepare delete '.$this->keys($laptop, $reset).' for '.TicketPolicy::LEAD, ...$this->calls('delete', $laptop, $reset)], $this->tickets->calls);
    }

    #[Test]
    public function it_prepares_the_row_checks_of_an_action_once_per_chunk(): void
    {
        ['Printer jam' => $jam, 'Printer toner' => $toner] = $this->seedTickets();
        ReviewTickets::$ability = 'escalate';

        $this->postJson('/prepared-tickets/actions/review-tickets', ['resources' => [$jam->id, $toner->id]])->assertOk();

        $this->assertSame(['viewAny', 'prepare escalate '.$this->keys($jam, $toner).' for guest', ...$this->calls('escalate', $jam, $toner)], $this->tickets->calls);
        $this->assertSame([[$jam->id, $toner->id]], ReviewTickets::$runs);
    }

    #[Test]
    public function it_prepares_the_rows_a_mutation_reads_and_leaves_its_changed_rows_and_links_out(): void
    {
        ['Printer jam' => $jam, 'Printer toner' => $toner, 'Password reset' => $reset] = $this->seedTickets();
        Tag::create(['id' => 1, 'name' => 'Hardware']);

        $this->postJson('/prepared-tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $jam->id, 'attributes' => ['title' => 'Printer fixed']],
            ['operation' => 'update', 'key' => $toner->id, 'relations' => [
                'team' => ['operation' => 'attach', 'key' => $reset->team_id],
                'tags' => [['operation' => 'attach', 'key' => 1]],
            ]],
        ]])->assertOk();

        $this->assertSame(['update '.$jam->id, 'update '.$toner->id], array_values(array_filter($this->tickets->calls, fn (string $call): bool => str_starts_with($call, 'update'))));
        $this->assertContains('attachTag '.$toner->id.' 1', $this->tickets->calls);
        $this->assertSame([], array_values(array_filter($this->tickets->calls, fn (string $call): bool => str_starts_with($call, 'prepare'))));
        $this->assertSame(['prepare view '.$reset->team_id, 'view '.$reset->team_id], $this->teams->calls);
    }

    #[Test]
    public function it_prepares_only_the_rows_that_the_memo_has_not_decided(): void
    {
        $tickets = $this->seedTickets();
        $authorizer = $this->authorizer();
        $resource = new PreparedTicketResource;

        $authorizer->inspect(new Check($resource, 'update', $tickets['Printer jam']));
        $authorizer->inspectEach($resource, 'update', collect($tickets));
        $authorizer->inspectEach($resource, 'update', collect($tickets));

        [$jam, $reset, $toner, $laptop] = array_values($tickets);
        $this->assertSame([
            'prepare update '.$jam->id.' for guest',
            'update '.$jam->id,
            'prepare update '.$this->keys($reset, $toner, $laptop).' for guest',
            ...$this->calls('update', $reset, $toner, $laptop),
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_prepares_only_the_rows_that_the_store_has_not_decided(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset] = $this->seedTickets();
        $resource = new PreparedTicketResource;
        $stored = new Check($resource, 'update', $reset);
        $authorizer = new Authorizer(Gate::forUser(null), $this->store([$stored->key() => new Decision(false, 'Stored refusal.')]));

        $decisions = $authorizer->inspectEach($resource, 'update', collect([$jam, $reset]));

        $this->assertSame(['prepare update '.$jam->id.' for guest', 'update '.$jam->id], $this->tickets->calls);
        $this->assertSame([null, 'Stored refusal.'], array_map(fn (Decision $decision): ?string => $decision->message, $decisions));
    }

    #[Test]
    public function it_prepares_nothing_when_every_row_is_decided(): void
    {
        ['Printer jam' => $jam] = $this->seedTickets();
        $resource = new PreparedTicketResource;
        $stored = new Check($resource, 'update', $jam);
        $authorizer = new Authorizer(Gate::forUser(null), $this->store([$stored->key() => Decision::allow()]));

        $authorizer->inspectEach($resource, 'update', collect([$jam]));

        $this->assertSame([], $this->tickets->calls);
    }

    #[Test]
    public function it_leaves_class_checks_drafts_changed_rows_and_link_checks_unprepared(): void
    {
        ['Printer jam' => $jam, 'Printer toner' => $toner, 'Password reset' => $reset] = $this->seedTickets();
        $resource = new PreparedTicketResource;
        $tag = Tag::create(['id' => 1, 'name' => 'Hardware']);
        $changed = $reset->fill(['title' => 'Password changed']);

        $this->authorizer()->inspectAll([
            Check::onClass($resource, 'viewAny'),
            new Check($resource, 'create', new Ticket(['title' => 'Draft'])),
            new Check($resource, 'update', $changed),
            new Check($resource, 'attachTag', $jam, related: [$tag]),
            new Check($resource, 'update', $toner),
        ]);

        $this->assertSame([
            'prepare update '.$toner->id.' for guest',
            'viewAny',
            'create',
            'update '.$reset->id,
            'attachTag '.$jam->id.' '.$tag->id,
            'update '.$toner->id,
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_lets_the_exception_of_the_hook_through_before_any_policy_check(): void
    {
        $tickets = $this->seedTickets();
        $failing = new class extends TicketResource
        {
            #[\Override]
            public function prepareAuthorization(string $ability, Collection $rows): void
            {
                throw new LogicException('The preload failed.');
            }
        };
        $authorizer = $this->authorizer();

        try {
            $authorizer->inspectEach($failing, 'update', collect($tickets));
            $this->fail('The hook did not throw.');
        } catch (LogicException $exception) {
            $this->assertSame('The preload failed.', $exception->getMessage());
        }

        $this->assertSame([], $this->tickets->calls);
        $authorizer->inspectEach(new PreparedTicketResource, 'update', collect($tickets));
        $this->assertSame('prepare update '.$this->keys(...array_values($tickets)).' for guest', $this->tickets->calls[0]);
    }

    #[Test]
    public function it_answers_the_policy_from_the_preloaded_rows_without_a_query_per_row(): void
    {
        $this->app->instance(TicketPolicy::class, $this->tickets = new TeamReadingTicketPolicy);
        $tickets = array_values($this->seedTickets());
        $payload = ['search' => ['gates' => ['escalate'], 'sorts' => [['field' => 'id']]]];
        $evaluated = 0;
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$evaluated): void {
            $evaluated += $event->ability === 'escalate' ? 1 : 0;
        });

        $plain = $this->teamQueries(fn () => $this->postJson('/api/tickets/search', $payload)->assertOk());
        $preloaded = $this->teamQueries(fn () => $this->postJson('/preloaded-tickets/search', $payload)->assertOk());

        $this->assertSame([4, 1], [$plain[0], $preloaded[0]]);
        $this->assertSame(8, $evaluated);
        $this->assertSame([...$this->calls('escalate', ...$tickets), ...$this->calls('escalate', ...$tickets)], array_values(array_filter($this->tickets->calls, fn (string $call): bool => $call !== 'viewAny')));
        $this->assertSame($plain[1], $preloaded[1]);
        $this->assertSame(['allowed' => false, 'message' => 'Only billing tickets are escalated.'], $preloaded[1][1]['gates']['authorized_to_escalate']);
    }

    #[Test]
    public function it_keeps_the_page_of_a_resource_without_the_hook_unchanged(): void
    {
        $tickets = array_values($this->seedTickets());
        $payload = ['search' => ['gates' => ['update', 'delete'], 'includes' => [['relation' => 'team', 'gates' => ['view']]], 'sorts' => [['field' => 'id']]]];

        $plain = $this->postJson('/api/tickets/search', $payload)->assertOk();

        $this->assertSame(['viewAny', ...$this->rowCalls(['update', 'delete'], ...$tickets)], $this->tickets->calls);
        $this->tickets->calls = [];
        $prepared = $this->postJson('/prepared-tickets/search', $payload)->assertOk();
        $this->assertSame($plain->json(), $prepared->json());
    }

    /**
     * @param  callable(): mixed  $request
     * @return array{int, list<array<string, mixed>>}
     */
    private function teamQueries(callable $request): array
    {
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries += preg_match('/from\s+["`]?teams["`]?/i', $query->sql);
        });
        $response = $request();
        $counted = $queries;
        $queries = 0;

        return [$counted, $response->json('data')];
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function rowCalls(array $abilities, Ticket ...$tickets): array
    {
        $calls = [];

        foreach ($tickets as $ticket) {
            foreach ($abilities as $ability) {
                $calls[] = $ability.' '.$ticket->id;
            }
        }

        return $calls;
    }

    private function keys(Ticket ...$tickets): string
    {
        return implode(',', array_map(fn (Ticket $ticket): int => $ticket->id, $tickets));
    }

    /** @param array<string, Decision> $held */
    private function store(array $held): DecisionStore
    {
        return new class($held) implements DecisionStore
        {
            /** @param array<string, Decision> $held */
            public function __construct(private readonly array $held) {}

            #[\Override]
            public function many(array $checks): array
            {
                return array_intersect_key($this->held, $checks);
            }

            #[\Override]
            public function putMany(array $checks, array $decisions): void {}
        };
    }

    private function authorizer(): Authorizer
    {
        return new Authorizer(Gate::forUser(null));
    }
}
