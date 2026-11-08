<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\HookedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LeadTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\SharedTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TrashableLeadTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Orchestra\Testbench\Attributes\WithConfig;
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
    #[WithConfig('rest.precognition.enabled', true)]
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
            #[\Override]
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
    public function it_runs_describing_once_listing_is_allowed(): void
    {
        Rest::resource('hooked-tickets', HookedTicketController::class)->register();

        $this->getJson('/hooked-tickets')->assertOk();

        $this->assertSame(['viewAny', 'describing', 'create'], $this->tickets->calls);
    }

    #[Test]
    public function it_does_not_run_describing_for_a_user_who_cannot_list(): void
    {
        Rest::resource('hooked-tickets', HookedTicketController::class)->register();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->getJson('/hooked-tickets')->assertForbidden()->assertExactJson(['message' => 'Tickets are not listed.']);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_listing_before_a_mutation(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->postJson('/api/tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => $jam->id, 'attributes' => ['title' => 'Paper jam']]]])
            ->assertOk()
            ->assertExactJson(['operations' => [['operation' => 'update', 'key' => $jam->id]]]);

        $this->assertSame(['viewAny', ...$this->calls('update', $jam)], $this->tickets->calls);
    }

    /** @return array<string, array{string, string, array<string, mixed>, bool, string}> */
    public static function allowedWrites(): array
    {
        return [
            'mutate' => ['POST', '/trashable-tags/mutate', ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Billing']]]], false, 'update 1'],
            'destroy' => ['DELETE', '/trashable-tags', ['resources' => [1]], false, 'delete 1'],
            'restore' => ['POST', '/trashable-tags/restore', ['resources' => [1]], true, 'restore 1'],
            'force destroy' => ['DELETE', '/trashable-tags/force', ['resources' => [1]], true, 'forceDelete 1'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('allowedWrites')]
    public function it_checks_listing_before_the_rows_of_a_write(string $method, string $uri, array $payload, bool $trashed, string $call): void
    {
        Rest::resource('trashable-tags', TagController::class)->withSoftDeletes()->register();
        $tag = Tag::create(['id' => 1, 'name' => 'Urgent']);

        if ($trashed) {
            $tag->delete();
        }

        $this->json($method, $uri, $payload)->assertOk();

        $this->assertSame(['viewAny', $call], $this->tags->calls);
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function invalidWrites(): array
    {
        return [
            'mutate' => ['POST', '/trashable-tags/mutate', ['mutate' => [['operation' => 'update', 'key' => 4, 'attributes' => ['nope' => 1], 'relations' => ['nothing' => []]]]]],
            'destroy' => ['DELETE', '/trashable-tags', ['resources' => ['abc', 4]]],
            'restore' => ['POST', '/trashable-tags/restore', ['resources' => ['abc', 4]]],
            'force destroy' => ['DELETE', '/trashable-tags/force', ['resources' => ['abc', 4]]],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('invalidWrites')]
    public function it_refuses_an_invalid_write_of_a_user_who_cannot_list_before_validating_it(string $method, string $uri, array $payload): void
    {
        Rest::resource('trashable-tags', TagController::class)->withSoftDeletes()->register();
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');
        $this->logQueries();

        $this->json($method, $uri, $payload)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tags are not listed.']);

        $this->assertNoQueries();
        $this->assertSame(['viewAny'], $this->tags->calls);
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('invalidWrites')]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_refuses_a_precognitive_write_of_a_user_who_cannot_list(string $method, string $uri, array $payload): void
    {
        Rest::resource('trashable-tags', TagController::class)->withSoftDeletes()->register();
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');
        $this->logQueries();

        $this->json($method, $uri, $payload, ['Precognition' => 'true'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tags are not listed.']);

        $this->assertNoQueries();
        $this->assertSame(['viewAny'], $this->tags->calls);
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
        Rest::resource('lead-tickets', TrashableLeadTicketController::class)->withSoftDeletes()->register();
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
}
