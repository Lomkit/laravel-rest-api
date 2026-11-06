<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RowVersionsTest extends HelpdeskTestCase
{
    use CapturesQueries;

    private const array CLOSED = ['allowed' => false, 'message' => 'Closed tickets are read-only.'];

    private const array ALLOWED = ['allowed' => true, 'message' => null];

    /** @var list<string> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            if ($event->seconds !== null) {
                $this->written[] = $event->key;
            }
        });
    }

    #[Test]
    public function it_evaluates_a_row_again_once_eloquent_changes_it(): void
    {
        $this->travelTo('2026-11-06 10:00:00');
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $answers = [];

        foreach (['10:00:01', '10:00:02', '10:00:32', '10:00:33', '10:00:34'] as $time) {
            $this->travelTo('2026-11-06 '.$time);

            if ($time === '10:00:32') {
                $jam->update(['status' => 'closed']);
            }

            $answers[] = $this->updateGateOfJam();
        }

        $this->assertSame([self::ALLOWED, self::ALLOWED, self::CLOSED, self::CLOSED, self::CLOSED], $answers);
        $this->assertSame(['viewAny', ...$this->calls('update', $laptop, $toner, $reset, $jam, $jam, $jam)], $this->tickets->calls);
    }

    #[Test]
    public function it_stores_no_decision_on_a_row_written_in_the_current_second(): void
    {
        $this->travelTo('2026-11-06 10:00:00.200');
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();

        $this->travelTo('2026-11-06 10:00:00.500');
        $before = $this->updateGateOfJam();
        $this->assertCount(1, $this->written);
        $this->travelTo('2026-11-06 10:00:00.700');
        $jam->update(['status' => 'closed']);
        $this->travelTo('2026-11-06 10:00:01.000');
        $after = $this->updateGateOfJam();

        $this->assertSame([self::ALLOWED, self::CLOSED], [$before, $after]);
        $this->assertSame(['viewAny', ...$this->calls('update', $laptop, $toner, $reset, $jam, $laptop, $toner, $reset, $jam)], $this->tickets->calls);
        $this->assertDatabaseHas('tickets', ['id' => $jam->id, 'status' => 'closed', 'updated_at' => '2026-11-06 10:00:00']);
    }

    /** @param list<string> $gates */
    #[Test]
    #[DataProvider('gatedLevels')]
    public function it_selects_the_row_version_only_on_a_level_that_gates_a_cached_row_ability(string $uri, array $gates, string $select): void
    {
        $this->seedTickets();
        $this->captureQueries();

        $response = $this->postJson($uri, ['search' => ['gates' => $gates]])->assertOk();

        $this->assertSame([[$select.' from tickets order by tickets.id desc limit 50 offset 0', []]], $this->readsOf('tickets'));
        $this->assertSame(['id', 'team_id', 'title', 'status', 'gates'], array_keys($response->json('data.0')));
    }

    /** @return array<string, array{string, list<string>, string}> */
    public static function gatedLevels(): array
    {
        $columns = 'select tickets.id, tickets.team_id, tickets.title, tickets.status';

        return [
            'a row ability that the resource caches' => ['/api/cached-tickets/search', ['view'], $columns.', tickets.updated_at'],
            'a row ability that the resource does not cache' => ['/api/cached-tickets/search', ['escalate'], $columns],
            'a class-level ability' => ['/api/cached-tickets/search', ['create'], $columns],
            'a resource that caches nothing' => ['/api/tickets/search', ['view'], $columns],
        ];
    }

    #[Test]
    public function it_evaluates_a_relation_check_again_once_its_related_row_changes(): void
    {
        $this->travelTo('2026-11-06 10:00:00');
        $jam = $this->seedTickets()['Printer jam'];
        $hardware = Tag::create(['id' => 1, 'name' => 'Hardware']);

        foreach (['10:00:01' => 'attach', '10:00:02' => 'detach', '10:00:03' => 'attach', '10:00:04' => 'detach', '10:00:06' => 'attach'] as $time => $operation) {
            $this->travelTo('2026-11-06 '.$time);

            if ($time === '10:00:04') {
                $hardware->update(['name' => 'Hardware and cables']);
            }

            $this->postJson('/api/cached-tickets/mutate', ['mutate' => [
                ['operation' => 'update', 'key' => $jam->id, 'relations' => ['tags' => [['operation' => $operation, 'key' => $hardware->id]]]],
            ]])->assertOk();
        }

        [$update, $link] = ['update '.$jam->id, $jam->id.' '.$hardware->id];

        $this->assertSame([$update, "attachTag {$link}", $update, "detachTag {$link}", $update, $update, "detachTag {$link}", $update, "attachTag {$link}"], $this->tickets->calls);
    }

    #[Test]
    public function it_keeps_the_checks_on_a_row_that_the_request_wrote_out_of_the_store(): void
    {
        $this->travelTo('2026-11-06 10:00:00');
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $hardware = Tag::create(['id' => 1, 'name' => 'Hardware']);
        $this->travelTo('2026-11-06 10:00:01');

        $this->postJson('/api/cached-tickets/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $jam->id, 'relations' => ['tags' => [['operation' => 'attach', 'key' => $hardware->id]]]]],
            'response' => ['gates' => ['update']],
        ])
            ->assertOk()
            ->assertJsonPath('records.0.gates.authorized_to_update', self::ALLOWED);
        $this->assertCount(2, $this->written);
        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['update']]])->assertOk();

        $this->assertSame(['viewAny', ...$this->calls('update', $jam), 'attachTag '.$jam->id.' 1', ...$this->calls('update', $jam, $laptop, $toner, $reset, $jam)], $this->tickets->calls);
        $this->assertDatabaseHas('tickets', ['id' => $jam->id, 'updated_at' => '2026-11-06 10:00:00']);
    }

    #[Test]
    public function it_leaves_no_decision_on_a_created_row_after_a_rollback(): void
    {
        $this->travelTo('2026-11-06 10:00:00');
        ['Password reset' => $reset] = $this->seedTickets();
        $hardware = Tag::create(['id' => 1, 'name' => 'Hardware']);
        $urgent = Tag::create(['id' => 2, 'name' => 'Urgent']);
        $this->travelTo('2026-11-06 10:00:01');

        $this->postJson('/api/cached-tickets/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['team_id' => $reset->team_id, 'title' => 'Monitor flicker', 'status' => 'open'], 'relations' => ['tags' => [
                ['operation' => 'attach', 'key' => $hardware->id],
                ['operation' => 'attach', 'key' => $urgent->id],
            ]]],
        ]])->assertStatus(409);

        $created = explode(' ', $this->tickets->calls[2])[1];

        $this->assertSame(['create', 'create', "attachTag {$created} 1", "attachTag {$created} 2"], $this->tickets->calls);
        $this->assertCount(1, $this->written);
        $this->assertDatabaseMissing('tickets', ['title' => 'Monitor flicker']);
    }

    #[Test]
    public function it_stores_no_decision_on_a_row_written_in_the_second_its_request_started(): void
    {
        $this->travelTo('2026-11-06 10:00:00.200');
        ['Printer toner' => $toner] = $this->seedTickets();
        $this->travelTo('2026-11-06 10:00:00.900');
        $during = true;
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$during, $toner): void {
            $row = $event->arguments[0] ?? null;

            if ($during && $row instanceof Model && $row->is($toner)) {
                DB::table('tickets')
                    ->where('id', $toner->id)
                    ->update(['status' => 'closed']);
                $this->travelTo('2026-11-06 10:00:01.100');
            }
        });
        $payload = ['search' => ['gates' => ['update']]];

        $this->postJson('/api/cached-tickets/search', $payload)
            ->assertOk()
            ->assertJsonPath('data.1.gates.authorized_to_update', self::ALLOWED);
        $during = false;
        $this->travelTo('2026-11-06 10:00:02');

        $this->postJson('/api/cached-tickets/search', $payload)
            ->assertOk()
            ->assertJsonPath('data.1.gates.authorized_to_update', self::CLOSED);
    }

    #[Test]
    public function it_dispatches_no_gate_event_for_a_decision_read_from_the_store(): void
    {
        $events = [];
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$events): void {
            $events[] = $event->ability;
        });

        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->postJson('/api/cached-tickets/search')->assertOk();

        $this->assertSame(['viewAny'], $events);
    }

    /** @return array{allowed: bool, message: string|null} */
    private function updateGateOfJam(): array
    {
        return $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['update']]])
            ->assertOk()
            ->json('data.3.gates.authorized_to_update');
    }
}
