<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\HookedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LeadTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\SharedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EntryChecksTest extends HelpdeskTestCase
{
    #[Test]
    public function it_refuses_an_invalid_search_of_a_user_who_cannot_list_before_validating_it(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['limit' => 7, 'filters' => [['field' => 'secret', 'value' => 1]]]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);

        $this->assertNoQueries();
    }

    #[Test]
    #[DefineEnvironment('enablePrecognition')]
    public function it_refuses_a_precognitive_search_of_a_user_who_cannot_list(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/search', ['search' => ['limit' => 7]], ['Precognition' => 'true', 'Precognition-Validate-Only' => 'search'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);
    }

    #[Test]
    public function it_checks_the_endpoint_abilities_once_the_authorize_method_of_the_request_passes(): void
    {
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');
        $this->app->bind(SearchRequest::class, fn (): SearchRequest => new class extends SearchRequest
        {
            public function authorize(): bool
            {
                return true;
            }
        });

        $this->postJson('/api/tickets/search', ['search' => ['limit' => 7]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tickets are not listed.']);
    }

    #[Test]
    public function it_runs_the_details_hook_once_listing_is_allowed(): void
    {
        Rest::resource('hooked-tickets', HookedTicketController::class)->register();

        $this->getJson('/hooked-tickets')->assertOk();

        $this->assertSame(['viewAny', 'beforeDetails'], $this->tickets->calls);
    }

    #[Test]
    public function it_does_not_run_the_details_hook_for_a_user_who_cannot_list(): void
    {
        Rest::resource('hooked-tickets', HookedTicketController::class)->register();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->getJson('/hooked-tickets')->assertForbidden()->assertExactJson(['message' => 'Tickets are not listed.']);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_no_listing_for_a_mutation(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => $jam->id, 'attributes' => ['title' => 'Paper jam']]]])
            ->assertOk()
            ->assertExactJson(['created' => [], 'updated' => [$jam->id]]);

        $this->assertSame($this->calls('update', $jam), $this->tickets->calls);
    }

    /** @return array<string, array{string, string, string, list<string>}> */
    public static function leadEndpoints(): array
    {
        return [
            'mutate' => ['POST', '/lead-tickets/mutate', 'updateAny', ['updateAny']],
            'operate' => ['POST', '/lead-tickets/actions/close-tickets', 'runActions', ['viewAny', 'runActions']],
            'destroy' => ['DELETE', '/lead-tickets', 'deleteAny', ['deleteAny']],
            'restore' => ['POST', '/lead-tickets/restore', 'restoreAny', ['restoreAny']],
            'force destroy' => ['DELETE', '/lead-tickets/force', 'forceDeleteAny', ['forceDeleteAny']],
        ];
    }

    /** @param list<string> $calls */
    #[Test]
    #[DataProvider('leadEndpoints')]
    public function it_checks_the_abilities_a_resource_requires_for_an_endpoint_before_validating(string $method, string $uri, string $ability, array $calls): void
    {
        Rest::resource('lead-tickets', LeadTicketController::class)->withSoftDeletes()->register();
        $this->tickets->answers[$ability] = Response::deny('Only the helpdesk lead does this.');
        $this->logQueries();

        $this->json($method, $uri, ['resources' => ['one']])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Only the helpdesk lead does this.']);

        $this->assertNoQueries();
        $this->assertSame($calls, $this->tickets->calls);
    }

    #[Test]
    public function it_searches_without_listing_a_resource_whose_search_requires_no_ability(): void
    {
        Rest::resource('shared-tickets', SharedTicketController::class)->register();
        $this->seedTickets();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/shared-tickets/search', ['search' => [
            'filters' => [['field' => 'status', 'value' => 'open']],
            'sorts' => [['field' => 'title', 'direction' => 'asc']],
        ]])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.*.title', ['Printer jam', 'Printer toner']);

        $this->assertSame([], $this->tickets->calls);
    }

    #[Test]
    public function it_destroys_once_the_abilities_a_resource_requires_are_allowed(): void
    {
        Rest::resource('lead-tickets', LeadTicketController::class)->register();
        $jam = $this->seedTickets()['Printer jam'];

        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]))
            ->deleteJson('/lead-tickets', ['resources' => [$jam->id]])
            ->assertOk();

        $this->assertDatabaseMissing('tickets', ['id' => $jam->id]);
        $this->assertSame(['deleteAny', ...$this->calls('delete', $jam)], $this->tickets->calls);
    }

    protected function enablePrecognition($app): void
    {
        $app['config']->set('rest.precognition.enabled', true);
    }
}
