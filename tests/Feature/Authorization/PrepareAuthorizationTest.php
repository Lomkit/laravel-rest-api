<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\DecisionStore;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\EscalateTickets;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreloadedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTagController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTeamController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ScannedTicket;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TeamDirectory;
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

        EscalateTickets::reset();
        PreparedTicketResource::$received = [];
        ScannedTicket::$scans = 0;
        $this->app->instance(TeamDirectory::class, new TeamDirectory);
        Rest::resource('prepared-tickets', PreparedTicketController::class)->register();
        Rest::resource('preloaded-tickets', PreloadedTicketController::class)->register();
        Rest::resource('prepared-teams', PreparedTeamController::class)->register();
        Rest::resource('prepared-tags', PreparedTagController::class)->withSoftDeletes()->register();
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
    public function it_prepares_for_the_user_a_search_runs_as(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $lead = new GenericUser(['id' => TicketPolicy::LEAD]);

        $this->actingAs(new GenericUser(['id' => 7]));
        Rest::search(PreparedTicketResource::class)
            ->as($lead)
            ->payload(['filters' => [['field' => 'id', 'value' => $jam->id]], 'gates' => ['update']])
            ->render();

        $this->assertSame(['viewAny', 'prepare update '.$jam->id.' for '.TicketPolicy::LEAD, 'update '.$jam->id], $this->tickets->calls);
        $this->assertSame($lead, PreparedTicketResource::$received[0]->user);
        $this->assertInstanceOf(PreparedTicketResource::class, PreparedTicketResource::$received[0]->resource);
        $this->assertSame('update', PreparedTicketResource::$received[0]->ability);
    }

    #[Test]
    public function it_prepares_each_chunk_of_an_action_with_the_rows_of_that_chunk(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        EscalateTickets::$ability = 'escalate';

        $this->postJson('/prepared-tickets/actions/escalate-tickets', ['resources' => [$jam->id, $reset->id, $toner->id, $laptop->id]])->assertForbidden();

        $this->assertSame([
            'viewAny',
            'prepare escalate '.$this->keys($jam, $reset).' for guest',
            ...$this->calls('escalate', $jam, $reset),
            'prepare escalate '.$this->keys($toner, $laptop).' for guest',
            ...$this->calls('escalate', $toner, $laptop),
        ], $this->tickets->calls);
        $this->assertSame([], EscalateTickets::$runs);
    }

    #[Test]
    public function it_prepares_each_chunk_of_an_action_that_skips_the_refused_rows(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();

        $this->postJson('/prepared-tickets/actions/escalate-allowed-tickets', ['resources' => [$jam->id, $reset->id, $toner->id, $laptop->id]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys($jam, $reset).' for guest',
            ...$this->calls('update', $jam, $reset),
            'prepare update '.$this->keys($toner, $laptop).' for guest',
            ...$this->calls('update', $toner, $laptop),
        ], $this->tickets->calls);
        $this->assertSame([[$jam->id], [$toner->id]], EscalateTickets::$runs);
    }

    #[Test]
    public function it_prepares_the_rows_of_one_resource_at_two_levels_in_one_call(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();

        $response = $this->postJson('/prepared-tickets/search', ['search' => [
            'filters' => [['field' => 'id', 'operator' => 'in', 'value' => [$jam->id, $reset->id]]],
            'gates' => ['update'],
            'includes' => [['relation' => 'team.tickets', 'gates' => ['update']]],
            'sorts' => [['field' => 'id']],
        ]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys($jam, $reset, $toner, $laptop).' for guest',
            ...$this->calls('update', $jam, $toner, $reset, $laptop),
        ], $this->tickets->calls);
        $this->assertSame([true, false], array_column($response->json('data.*.gates.authorized_to_update'), 'allowed'));
        $included = [];

        foreach ($response->json('data') as $row) {
            foreach ($row['team']['tickets'] as $ticket) {
                $included[$ticket['id']] = $ticket['gates']['authorized_to_update']['allowed'];
            }
        }

        ksort($included);
        $this->assertSame([$jam->id => true, $reset->id => false, $toner->id => true, $laptop->id => false], $included);
    }

    #[Test]
    public function it_prepares_the_rows_of_a_restore_and_of_a_force_destroy(): void
    {
        [$hardware, $network] = [Tag::create(['name' => 'Hardware']), Tag::create(['name' => 'Network'])];
        $hardware->delete();
        $network->delete();
        $keys = $hardware->id.','.$network->id;

        $this->postJson('/prepared-tags/restore', ['resources' => [$hardware->id, $network->id]])->assertOk();
        $hardware->delete();
        $network->delete();
        $this->deleteJson('/prepared-tags/force', ['resources' => [$hardware->id, $network->id]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare restore '.$keys,
            ...$this->calls('restore', $hardware, $network),
            'viewAny',
            'prepare forceDelete '.$keys,
            ...$this->calls('forceDelete', $hardware, $network),
        ], $this->tags->calls);
    }

    #[Test]
    public function it_prepares_the_rows_a_mutation_deletes_or_views_and_leaves_a_row_it_changed_out(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        [$billing, $support] = [$jam->team_id, $reset->team_id];
        $mouse = Ticket::create(['team_id' => $support, 'title' => 'Mouse broken', 'status' => 'open']);

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $this->postJson('/prepared-tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $jam->id, 'relations' => [
                'team' => ['operation' => 'update', 'key' => $billing, 'attributes' => ['name' => 'Billing desk']],
            ]],
            ['operation' => 'update', 'key' => $toner->id, 'relations' => ['team' => ['operation' => 'attach', 'key' => $billing]]],
            ['operation' => 'update', 'key' => $mouse->id, 'relations' => ['team' => ['operation' => 'attach', 'key' => $support]]],
        ]])->assertOk();

        $this->assertSame(['prepare view '.$support, 'update '.$billing, 'view '.$billing, 'view '.$support], $this->teams->calls);
        $this->tickets->calls = [];
        $this->postJson('/prepared-teams/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $support, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => [$laptop->id]]]]],
        ]])->assertOk();
        $this->assertSame(['prepare delete '.$laptop->id.' for '.TicketPolicy::LEAD, 'delete '.$laptop->id], $this->tickets->calls);
    }

    #[Test]
    public function it_does_no_preparation_work_for_a_resource_without_the_hook(): void
    {
        $this->seedTickets();
        $tickets = ScannedTicket::query()->orderBy('id')->get();

        $plain = $this->authorizer()->inspectEach(new TicketResource, 'update', $tickets);

        $this->assertSame(0, ScannedTicket::$scans);
        $this->assertSame([null, 'Closed tickets are read-only.', null, 'Closed tickets are read-only.'], array_map(fn (Decision $decision): ?string => $decision->message, $plain));
        $this->authorizer()->inspectEach(new PreparedTicketResource, 'update', $tickets);
        $this->assertSame(4, ScannedTicket::$scans);
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
            public function prepareAuthorization(PendingChecks $checks): void
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
    public function it_answers_the_policy_from_a_lookup_read_once_without_a_query_per_row(): void
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
