<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Exceptions\InvalidConfigException;
use PHPUnit\Framework\Attributes\Test;

class GateConfigTest extends HelpdeskTestCase
{
    private const array GATED_SEARCH = ['search' => ['gates' => ['view', 'create']]];

    #[Test]
    public function it_puts_the_gates_under_the_key_and_the_names_of_the_config(): void
    {
        config([
            'rest.gates.key' => 'abilities',
            'rest.gates.names.authorized_to_view' => 'can_view',
            'rest.gates.names.authorized_to_create' => 'can_create',
        ]);
        $this->seedTickets();

        $this->postJson('/api/tickets/search', self::GATED_SEARCH)
            ->assertOk()
            ->assertJsonPath('data.0.abilities', ['can_view' => ['allowed' => true, 'message' => null]])
            ->assertJsonPath('meta.abilities', ['can_create' => ['allowed' => true, 'message' => null]])
            ->assertJsonMissingPath('data.0.gates');
    }

    #[Test]
    public function it_names_an_empty_gates_key(): void
    {
        config(['rest.gates.key' => '']);
        $this->seedTickets();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.key] option must be a non-empty string.'));

        $this->postJson('/api/tickets/search', self::GATED_SEARCH);
    }

    #[Test]
    public function it_names_a_gate_name_that_is_not_a_string(): void
    {
        config(['rest.gates.names.authorized_to_view' => null]);
        $this->seedTickets();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.names.authorized_to_view] option must be a string.'));

        $this->postJson('/api/tickets/search', self::GATED_SEARCH);
    }

    #[Test]
    public function it_names_a_gate_name_missing_from_the_config(): void
    {
        config(['rest.gates.names' => [
            'authorized_to_view' => 'authorized_to_view',
            'authorized_to_create' => 'authorized_to_create',
            'authorized_to_update' => 'authorized_to_update',
            'authorized_to_delete' => 'authorized_to_delete',
            'authorized_to_restore' => 'authorized_to_restore',
        ]]);
        $this->seedTickets();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new InvalidConfigException('The [rest.gates.names.authorized_to_force_delete] option must be a string.'));

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['view']]]);
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
}
