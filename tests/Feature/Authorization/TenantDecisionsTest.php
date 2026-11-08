<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TenantTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Tenancy\Tenancy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TenantDecisionsTest extends HelpdeskTestCase
{
    private const string REFUSAL = 'Tickets of this tenant are closed to you.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('tenant-tickets', TenantTicketController::class)->register();
        Tenancy::$connections = ['a' => config('database.default'), 'b' => config('database.default')];
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    #[\Override]
    protected function tearDown(): void
    {
        Tenancy::forget();

        parent::tearDown();
    }

    /** @param list<int> $statuses */
    #[Test]
    #[DataProvider('contexts')]
    public function it_keeps_a_decision_per_tenant_when_the_context_holds_the_tenant(string $uri, array $statuses): void
    {
        $this->refuseInTenantB('viewAny');
        $answers = [];

        foreach (['a', 'b'] as $tenant) {
            Tenancy::initialize($tenant);
            $answers[] = $this->postJson("{$uri}/search")->status();
        }

        $this->assertSame($statuses, $answers);
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    /** @return iterable<string, array{string, list<int>}> */
    public static function contexts(): iterable
    {
        yield 'the tenant in the context' => ['/tenant-tickets', [200, 403]];
        yield 'no tenant in the context' => ['/api/cached-tickets', [200, 200]];
    }

    #[Test]
    public function it_keeps_a_row_decision_per_tenant(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $this->travel(1)->seconds();
        $this->refuseInTenantB('delete');
        $gates = [];

        foreach (['a', 'b', 'a', 'b'] as $tenant) {
            Tenancy::initialize($tenant);
            $gates[] = $this->postJson('/tenant-tickets/search', ['search' => [
                'filters' => [['field' => 'id', 'value' => $jam->id]],
                'gates' => ['delete'],
            ]])
                ->assertOk()
                ->json('data.0.gates.authorized_to_delete');
        }

        $allowed = ['allowed' => true, 'message' => null];
        $refused = ['allowed' => false, 'message' => self::REFUSAL];
        $this->assertSame([$allowed, $refused, $allowed, $refused], $gates);
        $this->assertSame(['viewAny', ...$this->calls('delete', $jam), 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    #[DataProvider('destructions')]
    public function it_destroys_a_row_with_a_decision_of_its_own_tenant_only(string $uri, int $status, bool $destroyed): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $this->travel(1)->seconds();
        $this->refuseInTenantB('delete');

        Tenancy::initialize('a');
        $this->postJson("{$uri}/search", ['search' => ['gates' => ['delete']]])->assertOk();
        Tenancy::initialize('b');
        $response = $this->deleteJson($uri, ['resources' => [$jam->id]]);

        $this->assertSame([$status, $destroyed], [$response->status(), Ticket::query()->whereKey($jam->id)->doesntExist()]);
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function destructions(): iterable
    {
        yield 'the tenant in the context' => ['/tenant-tickets', 403, false];
        yield 'no tenant in the context' => ['/api/cached-tickets', 200, true];
    }

    #[Test]
    public function it_forgets_the_decisions_of_a_user_in_every_tenant(): void
    {
        $user = new GenericUser(['id' => 1]);

        $this->searchIn('a', 'b', 'a', 'b');
        Tenancy::initialize('a');
        Rest::forgetAuthorizationsFor($user);
        $this->searchIn('a', 'b');

        $this->assertSame(['viewAny', 'viewAny', 'viewAny', 'viewAny'], $this->tickets->calls);
    }

    private function refuseInTenantB(string $ability): void
    {
        Gate::before(fn (?Authenticatable $user, string $checked): ?Response => Tenancy::$tenant === 'b' && $checked === $ability ? Response::deny(self::REFUSAL) : null);
    }

    private function searchIn(string ...$tenants): void
    {
        foreach ($tenants as $tenant) {
            Tenancy::initialize($tenant);
            $this->postJson('/tenant-tickets/search')->assertOk();
        }
    }
}
