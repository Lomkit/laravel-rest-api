<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\BareTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\KeptTicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class LoadedColumnsTest extends HelpdeskTestCase
{
    private KeptTicketPolicy $kept;

    /** @var list<string|int> */
    private array $reads = [];

    /** @var list<array{int, int}> */
    private array $writes = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventAccessingMissingAttributes(false);

        Rest::resource('bare-tickets', BareTicketController::class)->register();
        Gate::policy(Ticket::class, KeptTicketPolicy::class);
        $this->app->instance(KeptTicketPolicy::class, $this->kept = new KeptTicketPolicy);
        Event::listen(RetrievingManyKeys::class, function (RetrievingManyKeys $event): void {
            $this->reads[] = str_starts_with((string) array_key_first($event->keys), 'rest.authorizations.stamp') ? 'stamps' : count($event->keys);
        });
        Event::listen(WritingManyKeys::class, function (WritingManyKeys $event): void {
            $this->writes[] = [$event->seconds ?? 0, count($event->keys)];
        });

        $this->travelTo('2026-11-06 10:00:00');
        Team::create(['id' => 1, 'name' => 'Support']);
        Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'closed']);
        $this->travelTo('2026-11-06 10:00:01');
    }

    #[Test]
    public function it_checks_a_row_again_when_a_destroy_loads_more_columns_than_the_cached_search(): void
    {
        $this->assertSame(['allowed' => true, 'message' => null], $this->deleteGate([['field' => 'id']]));
        [$this->reads, $this->writes] = [[], []];

        $this->deleteJson('/bare-tickets', ['resources' => [1]])
            ->assertForbidden()
            ->assertJsonPath('message', 'Closed tickets are kept.');

        $this->assertSame([['stamps', 1], [[300, 1]]], [$this->reads, $this->writes]);
        $this->assertSame(['viewAny', 'delete 1', 'delete 1'], $this->kept->calls);
        $this->assertDatabaseHas('tickets', ['id' => 1, 'status' => 'closed']);
    }

    #[Test]
    public function it_serves_a_cached_row_decision_to_a_search_that_loads_the_same_columns(): void
    {
        $first = $this->deleteGate([['field' => 'id']]);
        [$this->reads, $this->writes] = [[], []];
        $second = $this->deleteGate([['field' => 'id']]);

        $this->assertSame([['allowed' => true, 'message' => null], ['allowed' => true, 'message' => null]], [$first, $second]);
        $this->assertSame([['stamps', 1, 1], []], [$this->reads, $this->writes]);
        $this->assertSame(['viewAny', 'delete 1'], $this->kept->calls);
    }

    #[Test]
    #[WithConfig('rest.search.select_columns', false)]
    public function it_serves_a_full_row_gate_decision_to_a_destroy(): void
    {
        $this->assertSame(['allowed' => false, 'message' => 'Closed tickets are kept.'], $this->deleteGate());
        $search = [$this->reads, $this->writes];
        [$this->reads, $this->writes] = [[], []];

        $this->deleteJson('/bare-tickets', ['resources' => [1]])
            ->assertForbidden()
            ->assertJsonPath('message', 'Closed tickets are kept.');

        $this->assertSame([['stamps', 1, 1], [[300, 1], [300, 1]]], $search);
        $this->assertSame([['stamps', 1], []], [$this->reads, $this->writes]);
        $this->assertSame(['viewAny', 'delete 1'], $this->kept->calls);
    }

    /**
     * @param  list<array{field: string}>|null  $selects
     * @return array<string, mixed>
     */
    private function deleteGate(?array $selects = null): array
    {
        $search = ['filters' => [['field' => 'id', 'value' => 1]], 'gates' => ['delete']];

        $response = $this->postJson('/bare-tickets/search', ['search' => $selects === null ? $search : [...$search, 'selects' => $selects]])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        return $response->json('data.0.gates.authorized_to_delete');
    }
}
