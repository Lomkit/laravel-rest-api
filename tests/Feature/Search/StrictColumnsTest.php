<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\MappedTicketController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\MappedTicketResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PartialTicketController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\PartialTicketResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\SummarizedTicketController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\SummarizedTicketResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\WatchedTicketController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\WatchedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class StrictColumnsTest extends PackageTestCase
{
    private const array GATES = ['search' => ['selects' => [['field' => 'title']], 'gates' => ['update', 'escalate']]];

    private const string DECIDED = '{"current_page":1,"data":[{"title":"Paper tray","gates":{"authorized_to_update":{"allowed":true,"message":null},"authorized_to_escalate":{"allowed":true,"message":null}}},{"title":"Printer jam","gates":{"authorized_to_update":{"allowed":false,"message":"Closed tickets are read-only."},"authorized_to_escalate":{"allowed":false,"message":"Closed tickets are not escalated."}}}],"from":1,"last_page":1,"per_page":50,"to":2,"total":2,"meta":{}}';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('partial-tickets', PartialTicketController::class)->register();
        Rest::resource('summarized-tickets', SummarizedTicketController::class)->register();
        Rest::resource('mapped-tickets', MappedTicketController::class)->register();
        Rest::resource('watched-tickets', WatchedTicketController::class)->register();
        Team::query()->create(['id' => 1, 'name' => 'Billing']);
        Ticket::query()->create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'closed']);
        Ticket::query()->create(['id' => 2, 'team_id' => 1, 'title' => 'Paper tray', 'status' => 'open']);
        WatchedTicketResource::$statuses = [];
        Model::preventAccessingMissingAttributes(false);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Model::handleMissingAttributeViolationUsing(null);

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_a_gate_whose_policy_reads_a_column_that_the_search_did_not_load(): void
    {
        $response = $this->postJson('/partial-tickets/search', self::GATES);

        $this->assertMissingColumn($response, PartialTicketResource::class);
        $this->assertFalse(Model::preventsAccessingMissingAttributes());
    }

    #[Test]
    public function it_answers_the_gates_when_the_resource_always_selects_the_column(): void
    {
        $response = $this->postJson('/api/tickets/search', self::GATES);

        $response->assertOk();
        $this->assertSame(self::DECIDED, $response->getContent());
    }

    #[Test]
    public function it_answers_the_gates_on_full_rows_when_the_column_selection_is_off(): void
    {
        config(['rest.search.select_columns' => false]);

        $response = $this->postJson('/partial-tickets/search', self::GATES);

        $response->assertOk()->assertJsonPath('data.*.gates.authorized_to_update.allowed', [true, false]);
        $this->assertSame([true, false], $response->json('data.*.gates.authorized_to_escalate.allowed'));
    }

    #[Test]
    public function it_refuses_a_resolved_field_that_reads_a_column_that_the_search_did_not_load(): void
    {
        $response = $this->postJson('/summarized-tickets/search', ['search' => ['selects' => [['field' => 'title'], ['field' => 'summary']]]]);

        $this->assertMissingColumn($response, SummarizedTicketResource::class);
    }

    #[Test]
    public function it_refuses_a_map_that_reads_a_column_that_the_search_did_not_load(): void
    {
        $response = $this->postJson('/mapped-tickets/search', ['search' => ['selects' => [['field' => 'title']]]]);

        $this->assertMissingColumn($response, MappedTicketResource::class);
    }

    #[Test]
    public function it_refuses_a_searched_event_that_reads_a_column_that_the_search_did_not_load(): void
    {
        $response = $this->postJson('/watched-tickets/search', ['search' => ['selects' => [['field' => 'title']]]]);

        $this->assertMissingColumn($response, WatchedTicketResource::class);
        $this->assertSame([], WatchedTicketResource::$statuses);
    }

    #[Test]
    public function it_refuses_a_gate_of_the_written_records_whose_policy_reads_a_column_that_they_did_not_load(): void
    {
        $response = $this->postJson('/partial-tickets/mutate', [
            'mutate' => [['operation' => 'update', 'key' => 2, 'attributes' => ['title' => 'Paper tray empty']]],
            'response' => ['selects' => [['field' => 'title']], 'gates' => ['escalate']],
        ]);

        $this->assertMissingColumn($response, PartialTicketResource::class);
    }

    #[Test]
    public function it_renders_a_search_whose_code_reads_only_the_loaded_columns(): void
    {
        $response = $this->postJson('/partial-tickets/search', ['search' => ['selects' => [['field' => 'title']]]]);

        $response->assertOk();
        $this->assertSame('{"current_page":1,"data":[{"title":"Paper tray"},{"title":"Printer jam"}],"from":1,"last_page":1,"per_page":50,"to":2,"total":2,"meta":{}}', $response->getContent());
        $this->assertFalse(Model::preventsAccessingMissingAttributes());
    }

    #[Test]
    #[DataProvider('applicationChecks')]
    public function it_restores_the_check_of_the_application_after_a_search(bool $check): void
    {
        Model::preventAccessingMissingAttributes($check);

        $this->postJson('/partial-tickets/search', ['search' => ['selects' => [['field' => 'title']]]])->assertOk();
        $this->assertSame($check, Model::preventsAccessingMissingAttributes());

        $this->assertMissingColumn($this->postJson('/partial-tickets/search', self::GATES), PartialTicketResource::class);
        $this->assertSame($check, Model::preventsAccessingMissingAttributes());
    }

    /** @return array<string, array{bool}> */
    public static function applicationChecks(): array
    {
        return [
            'with the check of the application on' => [true],
            'with the check of the application off' => [false],
        ];
    }

    #[Test]
    public function it_lets_the_violation_callback_of_the_application_answer_a_read_of_a_column_that_the_search_did_not_load(): void
    {
        $reads = [];
        Model::handleMissingAttributeViolationUsing(function (Model $model, string $key) use (&$reads): string {
            $reads[] = $model::class.' '.$model->getKey().' '.$key;

            return 'closed';
        });

        $response = $this->postJson('/partial-tickets/search', self::GATES);

        $response->assertOk()->assertJsonPath('data.*.gates.authorized_to_update.allowed', [false, false]);
        $this->assertSame([Ticket::class.' 2 status', Ticket::class.' 2 status', Ticket::class.' 1 status', Ticket::class.' 1 status'], $reads);
    }

    private function assertMissingColumn(TestResponse $response, string $resource): void
    {
        $response->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $response->exception);
        $this->assertSame(
            "The [{$resource}] search did not load the column [status] that a policy or a field of [".Ticket::class.'] reads. Add it to alwaysSelect() of the resource or to requires() of the field, or set rest.search.select_columns to false.',
            $response->exception->getMessage(),
        );
        $this->assertInstanceOf(MissingAttributeException::class, $response->exception->getPrevious());
    }
}
