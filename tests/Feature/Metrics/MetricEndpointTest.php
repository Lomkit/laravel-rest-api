<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Illuminate\Auth\Access\Response;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TypedTicketBacklog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MetricEndpointTest extends HelpdeskTestCase
{
    private const array TITLE_ONLY = ['filters' => [['field' => 'title', 'value' => 'Printer jam']]];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetricTicketResource::reset();
        $this->seedTickets();
        Rest::resource('metric-tickets', MetricTicketController::class)
            ->middleware('auth')
            ->register();
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
    }

    /** @param array<string, mixed> $data */
    #[Test]
    #[DataProvider('results')]
    public function it_answers_the_result_of_a_custom_metric_under_its_type(string $metric, array $data): void
    {
        $this->postJson("/metric-tickets/metrics/{$metric}")
            ->assertOk()
            ->assertExactJson(['data' => $data]);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function results(): iterable
    {
        yield 'a gauge' => ['ticket-backlog', ['type' => 'gauge', 'value' => 4, 'max' => 10]];
        yield 'a breakdown' => ['tickets-by-status', ['type' => 'breakdown', 'groups' => [['status' => 'closed', 'total' => 2], ['status' => 'open', 'total' => 2]]]];
    }

    /** @param array<string, string> $body */
    #[Test]
    #[DataProvider('unknownMetrics')]
    public function it_answers_an_unknown_metric_through_the_route_of_unknown_metrics(string $metric, ?Response $listing, int $status, array $body): void
    {
        MetricTicketResource::$late = true;

        if ($listing !== null) {
            $this->tickets->answers['viewAny'] = $listing;
        }

        $this->postJson("/metric-tickets/metrics/{$metric}")
            ->assertStatus($status)
            ->assertExactJson($body);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    /** @return iterable<string, array{string, ?Response, int, array<string, string>}> */
    public static function unknownMetrics(): iterable
    {
        $denied = Response::deny('Tickets are not listed.');

        yield 'unknown, user who can list' => ['revenue', null, 404, ['message' => '']];
        yield 'unknown, user who cannot list' => ['revenue', $denied, 403, ['message' => 'Tickets are not listed.']];
        yield 'known, user who cannot list' => ['ticket-backlog', $denied, 403, ['message' => 'Tickets are not listed.']];
        yield 'declared after the routes, user who can list' => ['late-ticket-backlog', null, 404, ['message' => '']];
    }

    #[Test]
    public function it_checks_the_abilities_that_the_metric_adds(): void
    {
        Gate::define('viewReports', fn (?GenericUser $user): Response => Response::deny('Reports are for the helpdesk lead.'));

        $this->postJson('/metric-tickets/metrics/reported-ticket-backlog')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Reports are for the helpdesk lead.']);
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_runs_a_metric_without_abilities_for_a_guest(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/metric-tickets/metrics/guest-ticket-backlog')
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 2, 'max' => 10]]);
        $this->postJson('/metric-tickets/metrics/ticket-backlog')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertSame([], $this->tickets->calls);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, string>  $sql
     * @param  list<mixed>  $bindings
     */
    #[Test]
    #[DataProvider('perimeterSearches')]
    public function it_counts_the_rows_of_the_search_inside_the_perimeter(array $search, int $value, array $sql, array $bindings): void
    {
        $this->actingAs(new GenericUser(['id' => 7]));
        $this->logQueries();

        $this->postJson('/metric-tickets/metrics/ticket-backlog', ['search' => $search])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => $value, 'max' => 10]]);

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame($sql[DB::connection()->getDriverName()], $queries[0]['query']);
        $this->assertSame($bindings, $queries[0]['bindings']);
    }

    /** @return iterable<string, array{array<string, mixed>, int, array<string, string>, list<mixed>}> */
    public static function perimeterSearches(): iterable
    {
        yield 'no search' => [[], 2, [
            'sqlite' => 'select count(*) as "aggregate" from "tickets" where ("tickets"."status" = ?)',
            'mysql' => 'select count(*) as `aggregate` from `tickets` where (`tickets`.`status` = ?)',
            'pgsql' => 'select count(*) as "aggregate" from "tickets" where ("tickets"."status" = ?)',
        ], ['open']];
        yield 'a text and a filter' => [['text' => ['value' => 'toner'], 'filters' => [['field' => 'status', 'value' => 'open']]], 1, [
            'sqlite' => 'select count(*) as "aggregate" from "tickets" where ("tickets"."status" = ?) and (("tickets"."title" like ? escape \'!\')) and ("tickets"."status" = ?)',
            'mysql' => 'select count(*) as `aggregate` from `tickets` where (`tickets`.`status` = ?) and ((`tickets`.`title` like ? escape \'!\')) and (`tickets`.`status` = ?)',
            'pgsql' => 'select count(*) as "aggregate" from "tickets" where ("tickets"."status" = ?) and (("tickets"."title" ilike ? escape \'!\')) and ("tickets"."status" = ?)',
        ], ['open', '%toner%', 'open']];
    }

    #[Test]
    public function it_counts_the_rows_of_a_relation_filter_through_the_perimeter_of_the_related_resource(): void
    {
        $this->postJson('/metric-tickets/metrics/ticket-backlog', ['search' => ['filters' => [['field' => 'team.name', 'value' => 'Billing']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 2, 'max' => 10]]);

        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');

        $this->postJson('/metric-tickets/metrics/ticket-backlog', ['search' => ['filters' => [['field' => 'team.name', 'value' => 'Billing']]]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Teams are not listed.']);
    }

    #[Test]
    public function it_groups_the_rows_whatever_the_order_that_an_instruction_adds(): void
    {
        $this->postJson('/metric-tickets/metrics/tickets-by-status', ['search' => ['instructions' => [['name' => 'newest-first']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'breakdown', 'groups' => [['status' => 'closed', 'total' => 2], ['status' => 'open', 'total' => 2]]]]);
    }

    #[Test]
    #[DataProvider('pageKeys')]
    public function it_refuses_the_search_keys_that_shape_a_page(string $key, mixed $value): void
    {
        $this->logQueries();

        $this->postJson('/metric-tickets/metrics/ticket-backlog', ['search' => [$key => $value]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => "The search.{$key} field is prohibited.", 'errors' => ["search.{$key}" => ["The search.{$key} field is prohibited."]]]);
        $this->assertNoQueries();
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function pageKeys(): iterable
    {
        yield 'sorts' => ['sorts', [['field' => 'id']]];
        yield 'selects' => ['selects', [['field' => 'title']]];
        yield 'includes' => ['includes', [['relation' => 'team']]];
        yield 'aggregates' => ['aggregates', [['relation' => 'tags', 'type' => 'count']]];
        yield 'gates' => ['gates', ['update']];
        yield 'limit' => ['limit', 10];
        yield 'page' => ['page', 2];
    }

    #[Test]
    public function it_answers_422_before_any_query_when_searching_refuses_the_search_of_a_metric(): void
    {
        $this->logQueries();

        $this->postJson('/metric-tickets/metrics/ticket-backlog', ['search' => self::TITLE_ONLY])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The title filter requires a status filter.', 'errors' => ['search.filters' => ['The title filter requires a status filter.']]]);
        $this->assertNoQueries();
    }

    #[Test]
    #[DataProvider('unsearchedMetrics')]
    public function it_skips_searching_for_a_metric_that_opts_out_and_keeps_the_perimeter(string $metric): void
    {
        $this->postJson("/metric-tickets/metrics/{$metric}", ['search' => self::TITLE_ONLY])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 1, 'max' => 10]]);

        $this->actingAs(new GenericUser(['id' => 7]));

        $this->postJson("/metric-tickets/metrics/{$metric}", ['search' => ['filters' => [['field' => 'title', 'value' => 'Password reset']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 0, 'max' => 10]]);
        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    /** @return iterable<string, array{string}> */
    public static function unsearchedMetrics(): iterable
    {
        yield 'on its declaration' => ['unchecked-ticket-backlog'];
        yield 'on its class' => ['quiet-ticket-backlog'];
    }

    #[Test]
    public function it_refuses_a_result_that_holds_a_type_key(): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetricException::typeInResult(TypedTicketBacklog::class));

        $this->postJson('/metric-tickets/metrics/typed-ticket-backlog');
    }

    #[Test]
    public function it_describes_the_metrics_of_the_resource(): void
    {
        $this->getJson('/metric-tickets')
            ->assertOk()
            ->assertJsonPath('data.metrics', [
                ['name' => 'Ticket Backlog', 'uriKey' => 'ticket-backlog', 'type' => 'gauge', 'ranges' => [], 'meta' => []],
                ['name' => 'Tickets By Status', 'uriKey' => 'tickets-by-status', 'type' => 'breakdown', 'ranges' => [], 'meta' => []],
                ['name' => 'Guest Ticket Backlog', 'uriKey' => 'guest-ticket-backlog', 'type' => 'gauge', 'ranges' => [], 'meta' => []],
                ['name' => 'Reported Ticket Backlog', 'uriKey' => 'reported-ticket-backlog', 'type' => 'gauge', 'ranges' => [], 'meta' => []],
                ['name' => 'Unchecked Ticket Backlog', 'uriKey' => 'unchecked-ticket-backlog', 'type' => 'gauge', 'ranges' => [], 'meta' => []],
                ['name' => 'Quiet Ticket Backlog', 'uriKey' => 'quiet-ticket-backlog', 'type' => 'gauge', 'ranges' => [], 'meta' => []],
                ['name' => 'Typed Ticket Backlog', 'uriKey' => 'typed-ticket-backlog', 'type' => 'gauge', 'ranges' => [], 'meta' => []],
            ]);
    }
}
