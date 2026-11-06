<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithEnv;
use PHPUnit\Framework\Attributes\Test;

class DecisionStoreKeysTest extends HelpdeskTestCase
{
    /** @var list<string> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            $this->written[] = $event->key;
        });
    }

    #[Test]
    public function it_leaves_the_cache_alone_when_no_resource_declares_an_ability(): void
    {
        $this->seedTickets();
        $events = [];
        Event::listen(
            [RetrievingKey::class, RetrievingManyKeys::class, CacheHit::class, CacheMissed::class, WritingKey::class, WritingManyKeys::class, KeyWritten::class],
            function (object $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['view', 'update', 'create']]])->assertOk();
        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['view', 'update', 'create']]])->assertOk();

        $this->assertTrue(config('rest.authorizations.cache.enabled'));
        $this->assertSame([], $events);
        $this->assertCount(20, $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.store', 'missing')]
    public function it_resolves_the_store_only_for_a_declared_ability(): void
    {
        $this->postJson('/api/tickets/search')->assertOk();

        $this->withoutExceptionHandling();
        $this->expectExceptionObject(new InvalidArgumentException('Cache store [missing] is not defined.'));

        $this->postJson('/api/cached-tickets/search');
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.enabled', 'false')]
    public function it_leaves_the_cache_alone_when_its_option_is_the_string_false(): void
    {
        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->postJson('/api/cached-tickets/search')->assertOk();

        $this->assertSame([], $this->written);
        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    #[WithEnv('REST_AUTHORIZATIONS_CACHE_ENABLED', 'false')]
    public function it_turns_the_cache_off_from_the_environment(): void
    {
        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->postJson('/api/cached-tickets/search')->assertOk();

        $this->assertFalse(config('rest.authorizations.cache.enabled'));
        $this->assertSame([], $this->written);
        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'session', 'provider' => 'users'])]
    public function it_keeps_the_decisions_of_two_guards_apart(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $this->actingAs($user, 'web')
            ->postJson('/api/cached-tickets/search')
            ->assertOk();
        $this->actingAs($user, 'api')
            ->postJson('/api/cached-tickets/search')
            ->assertOk();
        $this->actingAs($user, 'web')
            ->postJson('/api/cached-tickets/search')
            ->assertOk();

        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'session', 'provider' => 'users'])]
    public function it_keeps_the_decisions_of_two_user_classes_with_one_key_apart(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]))
            ->postJson('/api/cached-tickets/search')
            ->assertOk();
        $this->actingAs(new GenericUser(['id' => 1]))
            ->postJson('/api/cached-tickets/search')
            ->assertOk();
        $this->actingAs(User::factory()->make(['id' => 1]))
            ->postJson('/api/cached-tickets/search')
            ->assertOk();

        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_the_record_of_a_creation_again_in_every_request(): void
    {
        $team = Team::create(['id' => 1, 'name' => 'Billing']);
        $payload = ['mutate' => [
            ['operation' => 'create', 'attributes' => ['team_id' => $team->id, 'title' => 'Printer jam', 'status' => 'open']],
        ]];

        $this->postJson('/api/cached-tickets/mutate', $payload)->assertOk();
        $this->postJson('/api/cached-tickets/mutate', $payload)->assertOk();

        $this->assertSame(['create', 'create', 'create'], $this->tickets->calls);
        $this->assertCount(1, $this->written);
    }

    #[Test]
    public function it_keeps_the_update_checks_of_mutate_out_of_the_store(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->postJson('/api/cached-tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => $jam->id, 'attributes' => ['title' => 'Printer fixed']]]])->assertOk();
        $this->postJson('/api/cached-tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => $jam->id, 'attributes' => ['title' => 'Printer jam']]]])->assertOk();

        $this->assertSame($this->calls('update', $jam, $jam), $this->tickets->calls);
        $this->assertSame([], $this->written);
    }

    #[Test]
    public function it_leaves_out_a_resource_that_disables_the_cache(): void
    {
        $this->postJson('/api/uncached-tickets/search')->assertOk();
        $this->postJson('/api/uncached-tickets/search')->assertOk();
        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->postJson('/api/cached-tickets/search')->assertOk();

        $this->assertSame(['viewAny', 'viewAny', 'viewAny'], $this->tickets->calls);
    }
}
