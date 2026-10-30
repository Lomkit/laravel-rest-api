<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\Test;

class DenialsTest extends HelpdeskTestCase
{
    private const array SEARCH = ['search' => [
        'includes' => [['relation' => 'team']],
        'aggregates' => [['relation' => 'tags', 'type' => 'count']],
    ]];

    #[Test]
    public function it_answers_with_the_message_and_the_status_of_the_first_denial_of_the_rules(): void
    {
        $this->tags->answers['viewAny'] = Response::denyWithStatus(423, 'Tags are being merged.');
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');

        $this->postJson('/api/tickets/search', self::SEARCH)
            ->assertStatus(423)
            ->assertExactJson(['message' => 'Tags are being merged.']);
    }

    #[Test]
    public function it_answers_not_found_when_the_policy_hides_the_resource(): void
    {
        $this->renderDenials();
        $this->teams->answers['viewAny'] = Response::denyAsNotFound();

        $this->postJson('/api/tickets/search', self::SEARCH)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
    }

    #[Test]
    public function it_renders_every_denial_with_the_opt_in_renderer(): void
    {
        $this->renderDenials();
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');

        $this->postJson('/api/tickets/search', self::SEARCH)->assertForbidden()->assertExactJson([
            'message' => 'Tags are not listed.',
            'denials' => [
                ['path' => 'search.aggregates.0', 'ability' => 'viewAny', 'message' => 'Tags are not listed.'],
                ['path' => 'search.includes.0', 'ability' => 'viewAny', 'message' => 'Teams are not listed.'],
            ],
        ]);
    }

    #[Test]
    public function it_renders_the_denial_of_the_endpoint_without_a_path(): void
    {
        $this->renderDenials();
        $this->tickets->answers['viewAny'] = Response::deny('Tickets are not listed.');

        $this->postJson('/api/tickets/search', self::SEARCH)->assertForbidden()->assertExactJson([
            'message' => 'Tickets are not listed.',
            'denials' => [['path' => '', 'ability' => 'viewAny', 'message' => 'Tickets are not listed.']],
        ]);
    }

    #[Test]
    public function it_gives_the_default_message_to_a_denial_without_one(): void
    {
        $this->renderDenials();
        $this->teams->answers['viewAny'] = Response::deny();

        $this->postJson('/api/tickets/search', self::SEARCH)->assertForbidden()->assertExactJson([
            'message' => 'This action is unauthorized.',
            'denials' => [['path' => 'search.includes.0', 'ability' => 'viewAny', 'message' => 'This action is unauthorized.']],
        ]);
    }

    #[Test]
    public function it_leaves_the_refused_update_of_a_row_to_laravel(): void
    {
        $this->renderDenials();
        $reset = $this->seedTickets()['Password reset'];

        $this->postJson('/api/tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => $reset->id, 'attributes' => ['title' => 'Password expired']]]])
            ->assertStatus(423)
            ->assertExactJson(['message' => 'Closed tickets are read-only.']);
    }

    #[Test]
    public function it_leaves_the_refused_attach_of_a_row_to_laravel(): void
    {
        $this->renderDenials();
        $jam = $this->seedTickets()['Printer jam'];
        $urgent = Tag::create(['name' => 'Urgent']);

        $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $jam->id, 'relations' => ['tags' => [['operation' => 'attach', 'key' => [$urgent->id]]]]],
        ]])->assertStatus(409)->assertExactJson(['message' => 'Urgent tickets are flagged by the escalation desk.']);
    }

    #[Test]
    public function it_renders_no_denials_without_the_opt_in_renderer(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');

        $this->postJson('/api/tickets/search', self::SEARCH)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Teams are not listed.']);
    }
}
