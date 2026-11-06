<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MisdeclaredCacheTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MisdeclaredCacheTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\Test;

class DeclaredAbilitiesTest extends HelpdeskTestCase
{
    #[Test]
    public function it_evaluates_an_ability_that_the_resource_does_not_declare_in_every_request(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        $payload = ['search' => ['gates' => ['view', 'escalate']]];

        $this->postJson('/api/cached-tickets/search', $payload)->assertOk();
        $this->tickets->calls = [];
        $this->postJson('/api/cached-tickets/search', $payload)
            ->assertOk()
            ->assertJsonPath('data.*.gates.authorized_to_view.allowed', [true, true, true, true])
            ->assertJsonPath('data.*.gates.authorized_to_escalate.allowed', [false, true, false, true]);

        $this->assertSame($this->calls('escalate', $laptop, $toner, $reset, $jam), $this->tickets->calls);
    }

    #[Test]
    public function it_serves_the_create_gate_of_a_search_from_the_store(): void
    {
        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['create']]])
            ->assertOk()
            ->assertJsonPath('meta.gates.authorized_to_create', ['allowed' => true, 'message' => null]);
        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['create']]])
            ->assertOk()
            ->assertJsonPath('meta.gates.authorized_to_create', ['allowed' => true, 'message' => null]);

        $this->assertSame(['viewAny', 'create'], $this->tickets->calls);
    }

    #[Test]
    public function it_serves_a_declared_ability_to_the_endpoint_that_enforces_it(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        $this->actingAs(new GenericUser(['id' => 1]));

        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['delete']]])
            ->assertOk()
            ->assertJsonPath('data.3.gates.authorized_to_delete', ['allowed' => true, 'message' => null]);
        $this->deleteJson('/api/cached-tickets', ['resources' => [$jam->id]])->assertOk();

        $this->assertSame(['viewAny', ...$this->calls('delete', $laptop, $toner, $reset, $jam)], $this->tickets->calls);
        $this->assertDatabaseMissing('tickets', ['id' => $jam->id]);
    }

    #[Test]
    public function it_keeps_a_relation_check_under_the_resource_that_declares_its_ability(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $hardware = Tag::create(['id' => 1, 'name' => 'Hardware']);
        $this->travel(1)->seconds();

        foreach (['attach', 'detach', 'attach'] as $operation) {
            $this->postJson('/api/cached-tickets/mutate', ['mutate' => [
                ['operation' => 'update', 'key' => $jam->id, 'relations' => ['tags' => [['operation' => $operation, 'key' => $hardware->id]]]],
            ]])->assertOk();
        }

        $this->assertSame([
            'update '.$jam->id,
            'attachTag '.$jam->id.' '.$hardware->id,
            'update '.$jam->id,
            'detachTag '.$jam->id.' '.$hardware->id,
            'update '.$jam->id,
        ], $this->tickets->calls);
        $this->assertSame(['view 1', 'view 1', 'view 1'], $this->tags->calls);
    }

    #[Test]
    public function it_reads_the_declared_abilities_once_per_request(): void
    {
        $definition = $this->contextFor(null)
            ->resource(CachedTicketResource::class)
            ->definition();

        $this->assertSame($definition->cachedAbilities(), $definition->cachedAbilities());
        $this->assertSame([300, 60, null], [$definition->cachedAbilities()->seconds('viewAny'), $definition->cachedAbilities()->seconds('view'), $definition->cachedAbilities()->seconds('escalate')]);
    }

    #[Test]
    public function it_refuses_a_misdeclared_duration_when_a_check_reads_it(): void
    {
        Rest::resource('misdeclared-cache-tickets', MisdeclaredCacheTicketController::class)->register();

        $this->withoutExceptionHandling();
        $this->expectExceptionObject(InvalidResourceException::invalidCacheDuration(MisdeclaredCacheTicketResource::class, 'viewAny'));

        $this->postJson('/misdeclared-cache-tickets/search');
    }
}
