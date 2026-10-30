<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\UnplannedCheckException;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Query\ScoutBuilder;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;

class StrictModeTest extends HelpdeskTestCase
{
    private const array TEAM_INCLUDE = ['search' => ['includes' => [['relation' => 'team']]]];

    #[Test]
    public function it_throws_on_a_check_the_request_did_not_plan(): void
    {
        $this->seedTickets();
        $this->withoutReach();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject($this->unplanned('viewAny', TeamResource::class));

        $this->postJson('/api/tickets/search', self::TEAM_INCLUDE);
    }

    #[Test]
    public function it_throws_on_a_relation_filter_the_request_did_not_plan(): void
    {
        $this->seedTickets();
        $this->withoutReach();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject($this->unplanned('viewAny', TeamResource::class));

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => 'team.name', 'value' => 'Billing']]]]);
    }

    #[Test]
    public function it_throws_on_a_creation_the_request_did_not_plan(): void
    {
        $this->withoutReach();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject($this->unplanned('create', TicketResource::class));

        $this->postJson('/api/tickets/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['title' => 'Monitor flicker', 'status' => 'open']]]]);
    }

    #[Test]
    public function it_evaluates_a_check_the_request_did_not_plan_outside_strict_mode(): void
    {
        $this->withoutReach();
        config(['rest.authorizations.strict' => false]);
        $this->seedTickets();

        $this->postJson('/api/tickets/search', self::TEAM_INCLUDE)->assertOk()->assertJsonPath('total', 4);

        $this->assertSame(['viewAny'], $this->teams->calls);
    }

    #[Test]
    public function it_refuses_a_check_the_request_did_not_plan_outside_strict_mode(): void
    {
        $this->withoutReach();
        config(['rest.authorizations.strict' => false]);
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->seedTickets();

        $this->postJson('/api/tickets/search', self::TEAM_INCLUDE)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Teams are not listed.']);
    }

    #[Test]
    public function it_throws_when_a_builder_searches_without_planning_the_listing(): void
    {
        $resource = $this->contextFor(null)->resource(TicketResource::class);

        $this->expectExceptionObject($this->unplanned('viewAny', TicketResource::class));

        (new Builder($resource))->search();
    }

    #[Test]
    public function it_throws_when_a_text_search_runs_without_planning_the_listing(): void
    {
        $resource = $this->contextFor(null)->resource(TicketResource::class);

        $this->expectExceptionObject($this->unplanned('viewAny', TicketResource::class));

        (new ScoutBuilder($resource))->search(['text' => ['value' => 'printer']]);
    }

    #[Test]
    public function it_searches_with_a_builder_once_the_listing_is_planned(): void
    {
        $this->seedTickets();
        $context = $this->contextFor(null);
        $resource = $context->resource(TicketResource::class);
        $context->authorizer()->plan([Check::onClass($resource, 'viewAny')]);

        $sorted = (new Builder($resource))->search(['sorts' => [['field' => 'title', 'direction' => 'asc']]]);
        $unsorted = (new Builder($resource))->search();

        $this->assertSame(['Laptop setup', 'Password reset', 'Printer jam', 'Printer toner'], $sorted->pluck('title')->all());
        $this->assertSame(['Laptop setup', 'Printer toner', 'Password reset', 'Printer jam'], $unsorted->pluck('title')->all());
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    /** @param class-string $resource */
    private function unplanned(string $ability, string $resource): UnplannedCheckException
    {
        return new UnplannedCheckException('The ['.$ability.'] check on ['.$resource.'] was not planned before validation. Record it in the reach of the request.');
    }

    private function withoutReach(): void
    {
        $this->app->bind(SearchRequest::class, fn (): SearchRequest => new class extends SearchRequest
        {
            #[\Override]
            protected function reach(): array
            {
                return [];
            }
        });
        $this->app->bind(MutateRequest::class, fn (): MutateRequest => new class extends MutateRequest
        {
            #[\Override]
            protected function reach(): array
            {
                return [];
            }
        });
    }
}
