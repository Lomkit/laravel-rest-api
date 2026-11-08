<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\CachedLabelController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\SealedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
#[WithConfig('rest.authorizations.cache.store', 'database')]
#[WithConfig('cache.stores.database.connection', null)]
class DecisionBatchesTest extends HelpdeskTestCase
{
    private const array ALLOWED = ['allowed' => true, 'message' => null];

    /** @var list<int|string> */
    private array $reads = [];

    /** @var list<array{int, int}> */
    private array $writes = [];

    private int $statements = 0;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('sealed-tickets', SealedTicketController::class)->register();
        Rest::resource('cached-labels', CachedLabelController::class)->register();
        Event::listen(RetrievingManyKeys::class, function (RetrievingManyKeys $event): void {
            $stamps = str_starts_with((string) array_key_first($event->keys), 'rest.authorizations.stamp');
            $this->reads[] = $stamps ? 'stamps' : count($event->keys);
        });
        Event::listen(WritingManyKeys::class, function (WritingManyKeys $event): void {
            $this->writes[] = [$event->seconds ?? 0, count($event->keys)];
        });
        DB::listen(function (QueryExecuted $query): void {
            if (preg_match('/\b(from|into|update)\s+["`]?cache["`]?/i', $query->sql) === 1) {
                $this->statements++;
            }
        });
    }

    #[Test]
    public function it_reads_the_class_level_decisions_of_the_details_once_after_the_entry(): void
    {
        $this->travel(1)->seconds();

        $cold = $this->getJson('/api/cached-tickets?with=authorizations')->assertOk();
        $this->assertSame([['stamps', 1, 1], [[300, 1], [300, 1]], 6], $this->traffic());
        $this->assertSame(['viewAny', 'create'], $this->tickets->calls);
        $this->tickets->calls = [];
        $warm = $this->getJson('/api/cached-tickets?with=authorizations')->assertOk();

        $this->assertSame([['stamps', 1, 1], [], 3], $this->traffic());
        $this->assertSame([], $this->tickets->calls);
        $this->assertSame(['viewAny' => self::ALLOWED, 'create' => self::ALLOWED], $warm->json('data.authorizations'));
        $this->assertSame($cold->json(), $warm->json());
    }

    #[Test]
    public function it_reads_the_gates_of_a_page_once_and_writes_them_once_per_duration(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        $payload = ['search' => ['gates' => ['view', 'update', 'delete', 'create']]];

        $cold = $this->postJson('/api/cached-tickets/search', $payload)->assertOk();
        $this->assertSame([['stamps', 1, 13], [[300, 1], [300, 9], [60, 4]], 7], $this->traffic());
        $this->assertCount(14, $this->tickets->calls);
        $this->tickets->calls = [];
        $warm = $this->postJson('/api/cached-tickets/search', $payload)->assertOk();

        $this->assertSame([['stamps', 1, 13], [], 3], $this->traffic());
        $this->assertSame([], $this->tickets->calls);
        $this->assertSame($cold->json(), $warm->json());
        $this->assertSame([$laptop->id, $toner->id, $reset->id, $jam->id], $warm->json('data.*.id'));
    }

    #[Test]
    public function it_reads_the_gates_of_every_include_level_with_the_page(): void
    {
        $this->seedLabels();
        $this->travel(1)->seconds();
        $evaluated = [];
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$evaluated): void {
            $evaluated[] = $event->ability;
        });
        $payload = ['search' => [
            'gates' => ['view'],
            'includes' => [
                ['relation' => 'labelable', 'types' => [Book::class => ['gates' => ['view', 'update']], Shelf::class => ['gates' => ['view']]]],
                ['relation' => 'labelable', 'alias' => 'target', 'types' => [Book::class => ['gates' => ['view']]]],
            ],
            'sorts' => [['field' => 'id']],
        ]];

        $this->postJson('/cached-labels/search', $payload)->assertOk();
        $this->assertSame([['stamps', 1, 9], [[300, 1], [60, 9]], 6], $this->traffic());
        $evaluated = [];
        $this->postJson('/cached-labels/search', $payload)
            ->assertOk()
            ->assertJsonPath('data.*.labelable.gates', [
                ['authorized_to_view' => self::ALLOWED, 'authorized_to_update' => self::ALLOWED],
                ['authorized_to_view' => self::ALLOWED, 'authorized_to_update' => self::ALLOWED],
                ['authorized_to_view' => self::ALLOWED],
                null,
            ])
            ->assertJsonPath('data.*.target.gates', [['authorized_to_view' => self::ALLOWED], ['authorized_to_view' => self::ALLOWED], null, null]);

        $this->assertSame([['stamps', 1, 9], [], 3], $this->traffic());
        $this->assertSame(['viewAny', 'viewAny'], $evaluated);
    }

    #[Test]
    public function it_reads_the_gates_of_the_records_of_a_mutation_in_one_read(): void
    {
        $this->seedLabels();
        $this->travel(1)->seconds();

        $this->postJson('/cached-labels/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => 1, 'attributes' => ['text' => 'classic novel']],
                ['operation' => 'update', 'key' => 2, 'attributes' => ['text' => 'romance']],
            ],
            'response' => ['gates' => ['view'], 'includes' => [['relation' => 'labelable', 'types' => [Book::class => ['gates' => ['view']]]]]],
        ])
            ->assertOk()
            ->assertJsonPath('records.*.labelable.gates.authorized_to_view', [self::ALLOWED, self::ALLOWED]);

        $this->assertSame([['stamps', 1, 2], [[300, 1], [60, 2]], 6], $this->traffic());
    }

    #[Test]
    public function it_reads_and_writes_the_link_checks_of_a_mutation_once(): void
    {
        ['Printer jam' => $jam] = $this->seedTickets();
        foreach ([1 => 'Hardware', 2 => 'Network', 3 => 'Printer'] as $id => $name) {
            Tag::create(['id' => $id, 'name' => $name]);
        }
        $this->travel(1)->seconds();
        $links = fn (string $operation): array => ['mutate' => [[
            'operation' => 'update',
            'key' => $jam->id,
            'relations' => ['tags' => [['operation' => $operation, 'key' => 1], ['operation' => $operation, 'key' => 2], ['operation' => $operation, 'key' => 3]]],
        ]]];

        $this->postJson('/api/cached-tickets/mutate', $links('attach'))->assertOk();
        $this->assertSame([['stamps', 1, 3], [[300, 1], [300, 3]], 6], $this->traffic());
        $this->postJson('/api/cached-tickets/mutate', $links('detach'))->assertOk();
        $this->assertSame([['stamps', 1], [], 2], $this->traffic());
        $this->tickets->calls = [];
        $this->postJson('/api/cached-tickets/mutate', $links('attach'))->assertOk();

        $this->assertSame([['stamps', 1, 3], [], 3], $this->traffic());
        $this->assertSame(['update '.$jam->id], $this->tickets->calls);
    }

    #[Test]
    public function it_reads_and_writes_the_rows_of_a_destroy_once(): void
    {
        ['Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();

        $this->actingAs(new GenericUser(['id' => 1]))
            ->deleteJson('/api/cached-tickets', ['resources' => [$laptop->id, $toner->id, $reset->id]])
            ->assertOk();

        $this->assertSame([['stamps', 1, 3], [[300, 1], [300, 3]], 7], $this->traffic());
        $this->assertSame(['viewAny', ...$this->calls('delete', $laptop, $toner, $reset)], $this->tickets->calls);
    }

    #[Test]
    public function it_keeps_the_decisions_of_a_destroy_that_one_of_its_rows_refuses(): void
    {
        ['Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        Gate::before(fn (?Authenticatable $user, string $ability, array $arguments): ?bool => $ability === 'delete' && $arguments[0] instanceof Model && $arguments[0]->is($reset) ? false : null);
        $this->actingAs(new GenericUser(['id' => 1]));
        $rows = ['resources' => [$laptop->id, $toner->id, $reset->id]];

        $this->deleteJson('/api/cached-tickets', $rows)->assertForbidden();
        $this->assertSame([['stamps', 1, 3], [[300, 1], [300, 3]], 7], $this->traffic());
        $this->deleteJson('/api/cached-tickets', $rows)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);

        $this->assertSame([['stamps', 1, 3], [], 3], $this->traffic());
        $this->assertSame(['viewAny', ...$this->calls('delete', $laptop, $toner)], $this->tickets->calls);
        $this->assertDatabaseCount('tickets', 4);
    }

    #[Test]
    public function it_runs_an_authorized_to_override_whatever_the_store_holds(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        $this->actingAs(new GenericUser(['id' => 1]));

        $this->postJson('/sealed-tickets/search', ['search' => ['gates' => ['delete']]])
            ->assertOk()
            ->assertJsonPath('data.*.gates.authorized_to_delete', [self::ALLOWED, self::ALLOWED, self::ALLOWED, self::ALLOWED]);
        $this->deleteJson('/sealed-tickets', ['resources' => [$jam->id]], ['X-Sealed' => '1'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Sealed tickets are kept.']);

        $this->assertSame(['viewAny', ...$this->calls('delete', $laptop, $toner, $reset, $jam)], $this->tickets->calls);
        $this->assertDatabaseHas('tickets', ['id' => $jam->id]);
    }

    #[Test]
    public function it_keeps_no_decision_on_a_row_written_while_its_page_was_rendered(): void
    {
        $this->travelTo('2026-11-06 10:00:00.200');
        ['Password reset' => $reset, 'Printer toner' => $toner] = $this->seedTickets();
        $this->travelTo('2026-11-06 10:00:00.900');
        $during = true;
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$during, $reset, $toner): void {
            $row = $event->arguments[0] ?? null;

            if (! $during || ! $row instanceof Model) {
                return;
            }

            if ($row->is($toner)) {
                DB::table('tickets')->where('id', $toner->id)->update(['status' => 'closed']);
            }

            if ($row->is($reset)) {
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
            ->assertJsonPath('data.1.gates.authorized_to_update', ['allowed' => false, 'message' => 'Closed tickets are read-only.']);
    }

    /** @return array{list<int|string>, list<array{int, int}>, int} */
    private function traffic(): array
    {
        $traffic = [$this->reads, $this->writes, $this->statements];
        [$this->reads, $this->writes, $this->statements] = [[], [], 0];

        return $traffic;
    }

    private function seedLabels(): void
    {
        Book::create(['id' => 1, 'title' => 'Dune']);
        Book::create(['id' => 2, 'title' => 'Emma']);
        Shelf::create(['id' => 1, 'name' => 'Classics']);
        Label::create(['id' => 1, 'text' => 'classic', 'labelable_type' => Book::class, 'labelable_id' => 1]);
        Label::create(['id' => 2, 'text' => 'novel', 'labelable_type' => Book::class, 'labelable_id' => 2]);
        Label::create(['id' => 3, 'text' => 'wall', 'labelable_type' => Shelf::class, 'labelable_id' => 1]);
        Label::create(['id' => 4, 'text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]);
    }
}
