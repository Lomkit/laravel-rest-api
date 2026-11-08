<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Exceptions\AuthorizationDenied;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class SearchChecksTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('teams')->insert([['id' => 1, 'name' => 'Billing'], ['id' => 2, 'name' => 'Support']]);

        foreach ([[1, 1, 'Printer jam', 'open'], [2, 2, 'Password reset', 'closed'], [3, 1, 'Printer toner', 'open']] as [$id, $team, $title, $status]) {
            DB::table('tickets')->insert(['id' => $id, 'team_id' => $team, 'title' => $title, 'status' => $status]);
        }

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
        EventLog::reset();
    }

    #[Test]
    public function it_throws_the_validation_errors_of_the_endpoint(): void
    {
        $search = [
            'filters' => [['field' => 'unknown', 'value' => 1], ['field' => 'status', 'operator' => 'near', 'value' => 'open']],
            'includes' => [['relation' => 'team', 'limit' => 10]],
            'limit' => 7,
        ];
        $response = $this->postJson('/api/tickets/search', ['search' => $search])->assertUnprocessable();

        $exception = $this->caught(fn () => Rest::search(TicketResource::class)
            ->as(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->payload($search)
            ->render());

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame($response->json('errors'), $exception->errors());
        $this->assertSame($response->json('message'), $exception->getMessage());
        $this->assertSame(['search.limit', 'search.filters.0.field', 'search.filters.1.operator', 'search.includes.0.limit'], array_keys($exception->errors()));
    }

    #[Test]
    public function it_validates_the_page_given_to_render(): void
    {
        $exception = $this->caught(fn () => Rest::search(TicketResource::class)
            ->as(null)
            ->render(page: 0));

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame(['search.page' => ['The search.page field must be at least 1.']], $exception->errors());
    }

    #[Test]
    public function it_throws_the_view_any_denial_of_the_endpoint(): void
    {
        $this->tickets->open = false;
        $this->withoutExceptionHandling();
        $endpoint = $this->caught(fn () => $this->postJson('/api/tickets/search'));

        $exception = $this->caught(fn () => Rest::search(TicketResource::class)
            ->as(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->page());

        $this->assertInstanceOf(AuthorizationDenied::class, $endpoint);
        $this->assertInstanceOf(AuthorizationDenied::class, $exception);
        $this->assertSame([423, 'The helpdesk is closed.'], [$exception->status(), $exception->getMessage()]);
        $this->assertSame($endpoint->toArray(), $exception->toArray());
        $this->assertSame([], EventLog::$lines);
    }

    #[Test]
    public function it_runs_searching_before_the_query_and_searched_after_it(): void
    {
        EventLog::listen();

        $body = Rest::search(EventTicketResource::class)
            ->as(null)
            ->payload(['filters' => [['field' => 'status', 'value' => 'open']], 'includes' => [['relation' => 'team']]])
            ->render();
        $page = Rest::search(EventTicketResource::class)
            ->as(null)
            ->payload(['filters' => [['field' => 'status', 'value' => 'open']]])
            ->page();

        $this->assertSame([3, 1], array_column($body['data'], 'id'));
        $this->assertSame([3, 1], $page->getCollection()->modelKeys());
        $this->assertSame([
            'searching',
            'sql select tickets',
            'sql select teams',
            'searched 3,1 of 2',
            'searching',
            'sql select tickets',
            'searched 3,1 of 2',
        ], EventLog::$lines);
        $this->assertInstanceOf(SearchContext::class, EventLog::$payloads[0]);
        $this->assertTrue(EventLog::$payloads[0]->filtersOn('status'));
    }

    #[Test]
    public function it_throws_the_refusal_of_searching_before_any_query(): void
    {
        EventLog::listen();

        $exception = $this->caught(fn () => Rest::search(EventTicketResource::class)
            ->as(null)
            ->payload(['filters' => [['field' => 'title', 'value' => 'Printer jam']]])
            ->render());

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame(['search.filters' => ['The title filter requires a status filter.']], $exception->errors());
        $this->assertSame(['searching'], EventLog::$lines);
    }

    private function caught(Closure $run): ?Throwable
    {
        try {
            $run();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }
}
