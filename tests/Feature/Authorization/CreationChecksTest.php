<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class CreationChecksTest extends HelpdeskTestCase
{
    #[Test]
    public function it_checks_each_created_resource_once_before_validating_the_payload(): void
    {
        $this->postJson('/api/tickets/mutate', ['mutate' => [
            $this->ticketWithTeam('Printer jam', 'Billing'),
            $this->ticketWithTeam('Password reset', 'Support', ['priority' => 'high']),
        ]])->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.1.attributes' => ['The priority attribute is not writable on create.'],
        ]);

        $this->assertSame([['create'], ['create']], [$this->tickets->calls, $this->teams->calls]);
    }

    #[Test]
    public function it_refuses_a_creation_of_an_invalid_payload_before_validating_it(): void
    {
        $this->renderDenials();
        $this->teams->answers['create'] = Response::deny('Teams are created by the helpdesk lead.');
        $this->logQueries();

        $this->postJson('/api/tickets/mutate', ['mutate' => [
            $this->ticketWithTeam('Printer jam', 'Billing'),
            $this->ticketWithTeam('Password reset', 'Support', ['priority' => 'high']),
        ]])->assertForbidden()->assertExactJson([
            'message' => 'Teams are created by the helpdesk lead.',
            'denials' => [
                ['path' => 'mutate.0.relations.team', 'ability' => 'create', 'message' => 'Teams are created by the helpdesk lead.'],
                ['path' => 'mutate.1.relations.team', 'ability' => 'create', 'message' => 'Teams are created by the helpdesk lead.'],
            ],
        ]);

        $this->assertNoQueries();
        $this->assertSame([['create'], ['create']], [$this->tickets->calls, $this->teams->calls]);
    }

    #[Test]
    public function it_creates_each_resource_with_one_check_per_created_resource(): void
    {
        $this->postJson('/api/tickets/mutate', ['mutate' => [
            $this->ticketWithTeam('Printer jam', 'Billing'),
            $this->ticketWithTeam('Password reset', 'Support'),
        ]])->assertOk();

        $this->assertSame(['Billing', 'Support'], Team::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Password reset', 'Printer jam'], Ticket::query()->orderBy('title')->pluck('title')->all());
        $this->assertSame([['create'], ['create']], [$this->tickets->calls, $this->teams->calls]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function ticketWithTeam(string $title, string $team, array $attributes = []): array
    {
        return [
            'operation' => 'create',
            'attributes' => [...['title' => $title, 'status' => 'open'], ...$attributes],
            'relations' => ['team' => ['operation' => 'create', 'attributes' => ['name' => $team]]],
        ];
    }
}
