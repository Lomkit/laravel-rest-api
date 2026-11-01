<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use PHPUnit\Framework\Attributes\Test;

class RemovedSwitchesTest extends HelpdeskTestCase
{
    #[Test]
    public function it_runs_the_policies_when_a_published_config_still_turns_authorizations_off(): void
    {
        config(['rest.authorizations.enabled' => false]);
        $this->seedTickets();
        $this->tickets->open = false;

        $this->postJson('/api/tickets/search')
            ->assertStatus(423)
            ->assertExactJson(['message' => 'The helpdesk is closed.']);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_returns_the_gates_when_a_published_config_still_turns_gates_off(): void
    {
        config(['rest.gates.enabled' => false]);
        ['Laptop setup' => $laptop] = $this->seedTickets();

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['view', 'create']]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $laptop->id)
            ->assertJsonPath('data.0.gates', ['authorized_to_view' => true])
            ->assertJsonPath('meta.gates', ['authorized_to_create' => true]);
    }

    #[Test]
    public function it_leaves_out_the_gates_of_a_resource_that_disables_them(): void
    {
        ['Laptop setup' => $laptop] = $this->seedTickets();

        $this->postJson('/api/ungated-tickets/search', ['search' => ['gates' => ['view', 'create']]])
            ->assertOk()
            ->assertJsonPath('data.0', ['id' => $laptop->id, 'team_id' => $laptop->team_id, 'title' => 'Laptop setup', 'status' => 'closed'])
            ->assertJsonPath('meta', []);

        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_answers_next_to_a_published_documentation_block(): void
    {
        config(['rest.documentation' => ['routing' => ['enabled' => true, 'path' => '/api-documentation', 'middlewares' => ['web']]]]);
        $this->seedTickets();

        $this->postJson('/api/tickets/search')
            ->assertOk()
            ->assertJsonPath('total', 4);
    }
}
