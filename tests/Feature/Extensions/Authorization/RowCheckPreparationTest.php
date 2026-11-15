<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Authorization;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\EscalateTickets;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\PreparedTicketResource;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ScannedTicket;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentedPreparingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\LatePreparingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PreparingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RowCheckLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondPreparingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SubjectTicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RowCheckPreparationTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RowCheckLog::reset();
        EscalateTickets::reset();
        PreparedTicketResource::$received = [];
        ScannedTicket::$scans = 0;
        Rest::resource('prepared-tickets', PreparedTicketController::class)->register();
    }

    #[\Override]
    protected function tearDown(): void
    {
        RowCheckLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_hands_the_rows_of_a_page_to_an_extension_once_per_ability_before_the_policy(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        PreparingExtension::$resources = [TicketResource::class];
        $rows = array_values($this->seedTickets());

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['update', 'delete'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([
            'viewAny',
            'ext first update '.$this->keys(...$rows).' for guest',
            'ext first delete '.$this->keys(...$rows).' for guest',
            ...$this->rowCalls(['update', 'delete'], ...$rows),
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_hands_the_hook_and_the_extensions_the_same_object(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $rows = array_values($this->seedTickets());

        $this->postJson('/prepared-tickets/search', ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys(...$rows).' for guest',
            'ext first update '.$this->keys(...$rows).' for guest',
            ...$this->calls('update', ...$rows),
        ], $this->tickets->calls);
        $this->assertCount(1, PreparingExtension::$received);
        $this->assertSame(PreparedTicketResource::$received[0], PreparingExtension::$received[0]);
    }

    #[Test]
    public function it_hands_the_extension_the_user_of_the_gate(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $jam = $this->seedTickets()['Printer jam'];
        $lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $search = ['filters' => [['field' => 'id', 'value' => $jam->id]], 'gates' => ['update']];

        $this->actingAs($lead)->postJson('/api/tickets/search', ['search' => $search])->assertOk();
        $this->actingAs(new GenericUser(['id' => 7]));
        $other = new GenericUser(['id' => 9]);
        Rest::search(TicketResource::class)->as($other)->payload($search)->render();

        $this->assertSame($lead, PreparingExtension::$received[0]->user);
        $this->assertSame($other, PreparingExtension::$received[1]->user);
        $this->assertSame(['viewAny', 'ext first update '.$jam->id.' for '.TicketPolicy::LEAD, 'update '.$jam->id, 'viewAny', 'ext first update '.$jam->id.' for 9', 'update '.$jam->id], $this->tickets->calls);
    }

    #[Test]
    public function it_hands_the_exact_ability_and_the_resource(): void
    {
        config(['rest.extensions' => [PreparingExtension::class], 'rest.gates.names.authorized_to_escalate' => 'can_escalate']);
        $jam = $this->seedTickets()['Printer jam'];

        $response = $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => 'id', 'value' => $jam->id]], 'gates' => ['escalate']]])->assertOk();

        $this->assertSame(['allowed' => true, 'message' => null], $response->json('data.0.gates.can_escalate'));
        $checks = PreparingExtension::$received[0];
        $this->assertSame('escalate', $checks->ability);
        $this->assertInstanceOf(TicketResource::class, $checks->resource);
        $this->assertInstanceOf(Collection::class, $checks->rows);
        $this->assertSame([$jam->id], $checks->rows->modelKeys());
    }

    #[Test]
    public function it_still_runs_the_policy_on_every_row(): void
    {
        $rows = array_values($this->seedTickets());
        $payload = ['search' => ['gates' => ['update', 'delete'], 'includes' => [['relation' => 'team', 'gates' => ['view']]], 'sorts' => [['field' => 'id']]]];

        $plain = $this->postJson('/api/tickets/search', $payload)->assertOk()->json();
        $plainCalls = [$this->tickets->calls, $this->teams->calls];
        [$this->tickets->calls, $this->teams->calls] = [[], []];
        config(['rest.extensions' => [PreparingExtension::class]]);
        $prepared = $this->postJson('/api/tickets/search', $payload)->assertOk()->json();

        $this->assertSame($plain, $prepared);
        $this->assertSame(['viewAny', ...$this->rowCalls(['update', 'delete'], ...$rows)], $plainCalls[0]);
        $this->assertSame($plainCalls[0], array_values(array_filter($this->tickets->calls, fn (string $call): bool => ! str_starts_with($call, 'ext '))));
        $this->assertSame($plainCalls[1], array_values(array_filter($this->teams->calls, fn (string $call): bool => ! str_starts_with($call, 'ext '))));
    }

    #[Test]
    public function it_leaves_the_rows_that_the_store_decided_out_for_an_extension(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        PreparingExtension::$resources = [CachedTicketResource::class];
        $rows = array_values($this->seedTickets());
        $this->travel(1)->seconds();
        $payload = ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]];

        $first = $this->postJson('/api/cached-tickets/search', $payload)->assertOk()->json();
        $second = $this->postJson('/api/cached-tickets/search', $payload)->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertSame(['viewAny', 'ext first update '.$this->keys(...$rows).' for guest', ...$this->calls('update', ...$rows)], $this->tickets->calls);
    }

    #[Test]
    public function it_prepares_each_chunk_of_an_action_with_an_extension(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();

        $this->postJson('/prepared-tickets/actions/escalate-allowed-tickets', ['resources' => [$jam->id, $reset->id, $toner->id, $laptop->id]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys($jam, $reset).' for guest',
            'ext first update '.$this->keys($jam, $reset).' for guest',
            ...$this->calls('update', $jam, $reset),
            'prepare update '.$this->keys($toner, $laptop).' for guest',
            'ext first update '.$this->keys($toner, $laptop).' for guest',
            ...$this->calls('update', $toner, $laptop),
        ], $this->tickets->calls);
        $this->assertSame([[$jam->id], [$toner->id]], EscalateTickets::$runs);
    }

    #[Test]
    public function it_does_no_preparation_work_without_a_hook_or_an_extension_that_applies(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $this->seedTickets();
        $tickets = ScannedTicket::query()->orderBy('id')->get();
        PreparingExtension::$resources = [TeamResource::class];
        $context = $this->contextFor(null);

        $context->authorizer()->inspectEach($context->resource(TicketResource::class), 'update', $tickets);

        $this->assertSame(0, ScannedTicket::$scans);
        $this->assertSame([], PreparingExtension::$received);
        PreparingExtension::$resources = [TicketResource::class];
        $context = $this->contextFor(null);
        $context->authorizer()->inspectEach($context->resource(TicketResource::class), 'update', $tickets);
        $this->assertSame(4, ScannedTicket::$scans);
        $this->assertCount(1, PreparingExtension::$received);
    }

    #[Test]
    public function it_builds_no_extension_to_check_rows_without_one_configured(): void
    {
        $this->seedTickets();
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            $resolved[] = $abstract;
        });

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['update']]])->assertOk();

        $this->assertSame([], array_values(array_filter(
            $resolved,
            fn (string $abstract): bool => class_exists($abstract) && is_subclass_of($abstract, Extension::class),
        )));
        $this->assertSame([], PreparingExtension::$received);
    }

    /** @param list<class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('configOrders')]
    public function it_calls_the_extensions_in_name_order_whatever_the_config_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $rows = array_values($this->seedTickets());

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([
            'viewAny',
            'ext second update '.$this->keys(...$rows).' for guest',
            'ext first update '.$this->keys(...$rows).' for guest',
            ...$this->calls('update', ...$rows),
        ], $this->tickets->calls);
        $this->assertSame(SecondPreparingExtension::$received[0], PreparingExtension::$received[0]);
    }

    /** @return iterable<string, array{list<class-string<Extension>>}> */
    public static function configOrders(): iterable
    {
        yield 'b before a' => [[LatePreparingExtension::class, SecondPreparingExtension::class]];
        yield 'a before b' => [[SecondPreparingExtension::class, LatePreparingExtension::class]];
    }

    #[Test]
    public function it_lets_the_exception_of_an_extension_through_before_any_policy_check(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $rows = array_values($this->seedTickets());
        PreparingExtension::$prepare = static function (): void {
            throw new LogicException('The grants are unreachable.');
        };
        $context = $this->contextFor(null);

        try {
            $context->authorizer()->inspectEach($context->resource(TicketResource::class), 'update', collect($rows));
            $this->fail('The extension did not throw.');
        } catch (LogicException $exception) {
            $this->assertSame('The grants are unreachable.', $exception->getMessage());
        }

        $this->assertSame(['ext first update '.$this->keys(...$rows).' for guest'], $this->tickets->calls);
    }

    #[Test]
    public function it_hands_the_augmented_rows_to_the_preparation_and_the_policy(): void
    {
        config(['rest.extensions' => [AugmentedPreparingExtension::class]]);
        $this->app->instance(TicketPolicy::class, $policy = new SubjectTicketPolicy);
        $this->seedTickets();

        $response = $this->postJson('/api/tickets/search', ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame('aug__flag', AugmentedPreparingExtension::$alias);
        $this->assertSame(['update'], AugmentedPreparingExtension::$gates);
        $this->assertCount(1, AugmentedPreparingExtension::$received);
        $rows = AugmentedPreparingExtension::$received[0]->rows;
        $this->assertCount(4, $rows);
        $this->assertSame(['seen', 'seen', 'seen', 'seen'], $rows->map(fn (Model $row): mixed => $row->getAttribute('aug__flag'))->all());
        $this->assertSame(array_map(fn (Model $row): array => [spl_object_id($row), 'seen'], $rows->all()), $policy->subjects);
        $this->assertSame(['id', 'team_id', 'title', 'status', 'gates'], array_keys($response->json('data.0')));
    }

    /** @param Closure(PendingChecks): mixed $change */
    #[Test]
    #[DataProvider('rowChanges')]
    public function it_refuses_an_extension_that_changes_a_prepared_row_and_names_the_registered_class(Closure $change): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $this->app?->bind(PreparingExtension::class, static fn (): PreparingExtension => new class extends PreparingExtension {});
        $this->seedTickets();
        PreparingExtension::$prepare = static function (PendingChecks $checks) use ($change): void {
            $change($checks);
        };
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/tickets/search', ['search' => ['gates' => ['update'], 'includes' => [['relation' => 'team']], 'sorts' => [['field' => 'id']]]]);
            $this->fail('The changed row was accepted.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertSame(InvalidAuthorizationExtensionException::changedRows('prep', PreparingExtension::class, TicketResource::class, 'update')->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(PreparingExtension::class));
        } finally {
            $this->assertSame(['viewAny'], array_values(array_filter($this->tickets->calls, fn (string $call): bool => ! str_starts_with($call, 'ext '))));
        }
    }

    /** @return iterable<string, array{Closure(PendingChecks): mixed}> */
    public static function rowChanges(): iterable
    {
        yield 'an attribute' => [static fn (PendingChecks $checks): Model => $checks->rows->firstOrFail()->setAttribute('title', 'Changed')];
        yield 'a removed relation' => [static fn (PendingChecks $checks): Model => $checks->rows->firstOrFail()->unsetRelation('team')];
    }

    #[Test]
    public function it_lets_an_extension_load_a_relation_of_an_included_model(): void
    {
        $this->seedTickets();
        $payload = ['search' => ['gates' => ['update'], 'includes' => [['relation' => 'team']], 'sorts' => [['field' => 'id']]]];
        $plain = $this->postJson('/api/tickets/search', $payload)->assertOk()->json();
        config(['rest.extensions' => [PreparingExtension::class]]);
        PreparingExtension::$prepare = static function (PendingChecks $checks): void {
            $checks->rows->each(fn (Model $row): mixed => $row->getRelation('team')->loadMissing('tickets'));
        };

        $prepared = $this->postJson('/api/tickets/search', $payload)->assertOk()->json();

        $this->assertSame($plain, $prepared);
        $this->assertTrue(PreparingExtension::$received[0]->rows->firstOrFail()->getRelation('team')->relationLoaded('tickets'));
    }

    #[Test]
    public function it_refuses_an_extension_that_changes_where_a_destroyed_row_is_written(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        ['Printer jam' => $jam, 'Password reset' => $reset] = $this->seedTickets();
        PreparingExtension::$prepare = static function (PendingChecks $checks): void {
            $checks->rows->each(static fn (Model $row): Model => $row->setTable('teams'));
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('prep', PreparingExtension::class, TicketResource::class, 'delete'));

        try {
            $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]))->deleteJson('/api/tickets', ['resources' => [$jam->id, $reset->id]]);
        } finally {
            $this->assertSame(4, Ticket::query()->count());
            $this->assertSame(2, Team::query()->count());
        }
    }

    #[Test]
    public function it_lets_an_extension_read_the_rows_and_load_a_relation_that_was_not_loaded(): void
    {
        $this->seedTickets();
        $payload = ['search' => ['gates' => ['update'], 'includes' => [['relation' => 'team']], 'sorts' => [['field' => 'id']]]];
        $plain = $this->postJson('/api/tickets/search', $payload)->assertOk()->json();
        config(['rest.extensions' => [PreparingExtension::class]]);
        $read = [];
        PreparingExtension::$prepare = static function (PendingChecks $checks) use (&$read): void {
            $checks->rows->loadMissing('tags');
            $read = $checks->rows->map(fn (Model $row): array => [$row->getAttribute('title'), $row->getRelation('team')->getAttribute('name')])->all();
        };

        $prepared = $this->postJson('/api/tickets/search', $payload)->assertOk()->json();

        $this->assertSame($plain, $prepared);
        $this->assertSame([['Printer jam', 'Billing'], ['Password reset', 'Support'], ['Printer toner', 'Billing'], ['Laptop setup', 'Support']], $read);
    }

    /** @param list<class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('configOrders')]
    public function it_lets_two_extensions_load_the_same_relation(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $this->seedTickets();
        $loads = static function (PendingChecks $checks): void {
            $checks->rows->load('tags');
        };
        PreparingExtension::$prepare = $loads;
        SecondPreparingExtension::$prepare = $loads;
        $payload = ['search' => ['gates' => ['update'], 'includes' => [['relation' => 'team']], 'sorts' => [['field' => 'id']]]];

        $this->postJson('/api/tickets/search', $payload)->assertOk()->assertJsonPath('data.*.gates.authorized_to_update.allowed', [true, false, true, false]);

        $this->assertCount(1, PreparingExtension::$received);
        $this->assertCount(1, SecondPreparingExtension::$received);
        $this->assertTrue(PreparingExtension::$received[0]->rows->every(fn (Model $row): bool => $row->relationLoaded('tags')));
    }

    #[Test]
    public function it_lets_an_extension_load_a_relation_that_the_hook_loaded(): void
    {
        config(['rest.extensions' => [PreparingExtension::class]]);
        $rows = array_values($this->seedTickets());
        $loads = static function (PendingChecks $checks): void {
            $checks->rows->load('tags');
        };
        PreparedTicketResource::$prepare = $loads;
        PreparingExtension::$prepare = $loads;

        $this->postJson('/prepared-tickets/search', ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([
            'viewAny',
            'prepare update '.$this->keys(...$rows).' for guest',
            'ext first update '.$this->keys(...$rows).' for guest',
            ...$this->calls('update', ...$rows),
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_refuses_the_second_extension_that_replaces_a_relation_loaded_before_the_preparation(): void
    {
        config(['rest.extensions' => [SecondPreparingExtension::class, LatePreparingExtension::class]]);
        $this->seedTickets();
        SecondPreparingExtension::$prepare = static function (PendingChecks $checks): void {
            $checks->rows->load('tags');
        };
        PreparingExtension::$prepare = static function (PendingChecks $checks): void {
            $checks->rows->load('team');
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('b', LatePreparingExtension::class, TicketResource::class, 'update'));

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['update'], 'includes' => [['relation' => 'team']], 'sorts' => [['field' => 'id']]]]);
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
}
