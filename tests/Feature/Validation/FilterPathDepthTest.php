<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterPathDepthTest extends HelpdeskTestCase
{
    private const string FOUR_RELATIONS = 'team.tickets.team.tickets.title';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $tickets = $this->seedTickets();
        Tag::create(['id' => 1, 'name' => 'Hardware']);
        $tickets['Printer jam']->tags()->attach(1, ['position' => 1]);
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('pathsAtTheLimit')]
    public function it_filters_through_as_many_relations_as_the_include_depth(array $filter): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => ['filters' => [$filter], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk();
        $this->assertSame(['Printer jam', 'Printer toner'], array_column($response->json('data'), 'title'));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function pathsAtTheLimit(): array
    {
        return [
            'three relations' => [['field' => 'team.tickets.team.name', 'value' => 'Billing']],
            'three relations and a pivot field' => [['field' => 'team.tickets.tags.pivot.position', 'value' => 1]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('longPaths')]
    public function it_refuses_a_filter_path_through_more_relations_than_the_include_depth(array $search, string $key): void
    {
        $message = "The {$key} field goes through more relations than the limit of 3.";
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => $search])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);

        $this->assertNoQueries();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function longPaths(): array
    {
        $filter = ['field' => self::FOUR_RELATIONS, 'value' => 'Printer jam'];
        $fromTeams = ['field' => 'tickets.team.tickets.team.name', 'value' => 'Billing'];

        return [
            'a filter' => [['filters' => [$filter]], 'search.filters.0.field'],
            'a filter and a pivot field' => [['filters' => [['field' => 'team.tickets.tags.labelledTickets.pivot.position', 'value' => 1]]], 'search.filters.0.field'],
            'a nested group' => [['filters' => [['nested' => [$filter]]]], 'search.filters.0.nested.0.field'],
            'a relation group' => [['filters' => [['relation' => 'team', 'nested' => [$fromTeams]]]], 'search.filters.0.nested.0.field'],
            'an include' => [['includes' => [['relation' => 'team', 'filters' => [$fromTeams]]]], 'search.includes.0.filters.0.field'],
            'an aggregate' => [['aggregates' => [['relation' => 'tags', 'type' => 'count', 'filters' => [
                ['field' => 'labelledTickets.team.tickets.team.name', 'value' => 'Billing'],
            ]]]], 'search.aggregates.0.filters.0.field'],
        ];
    }

    #[Test]
    public function it_refuses_a_long_filter_path_in_the_search_of_an_action(): void
    {
        $message = 'The search.filters.0.field field goes through more relations than the limit of 3.';

        $this->postJson('/api/tickets/actions/close-tickets', ['search' => ['filters' => [['field' => self::FOUR_RELATIONS, 'value' => 'Printer jam']]]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.field' => [$message]]]);

        $this->assertSame(['Printer jam' => 'open', 'Password reset' => 'closed', 'Printer toner' => 'open', 'Laptop setup' => 'closed'], $this->statuses());
    }

    #[Test]
    public function it_refuses_a_long_filter_path_in_the_search_of_a_metric(): void
    {
        MetricTicketResource::reset();
        Rest::resource('metric-tickets', MetricTicketController::class)->register();
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
        $message = 'The search.filters.0.field field goes through more relations than the limit of 3.';

        $this->postJson('/metric-tickets/metrics/ticket-backlog', ['search' => ['filters' => [['field' => self::FOUR_RELATIONS, 'value' => 'Printer jam']]]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.field' => [$message]]]);
    }

    #[Test]
    public function it_reads_the_limit_from_the_include_depth(): void
    {
        config(['rest.limits.depths.includes' => 4]);

        $response = $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => self::FOUR_RELATIONS, 'value' => 'Printer jam']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk();
        $this->assertSame(['Printer jam', 'Printer toner'], array_column($response->json('data'), 'title'));
    }

    #[Test]
    public function it_refuses_every_relation_path_at_an_include_depth_of_zero(): void
    {
        config(['rest.limits.depths.includes' => 0]);
        $message = 'The search.filters.1.field field goes through more relations than the limit of 0.';

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [
            ['field' => 'title', 'value' => 'Printer jam'],
            ['field' => 'team.name', 'value' => 'Billing'],
        ]]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.filters.1.field' => [$message]]]);
    }

    #[Test]
    public function it_keeps_the_invalid_field_error_for_a_long_path_that_reaches_no_field(): void
    {
        $message = 'The selected search.filters.0.field is invalid.';

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => 'team.tickets.team.tickets.summary', 'value' => 'Printer jam']]]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.field' => [$message]]]);
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        return Ticket::query()
            ->orderBy('id')
            ->pluck('status', 'title')
            ->all();
    }
}
