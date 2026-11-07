<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class ForgetAuthorizationsTest extends HelpdeskTestCase
{
    private const string EVERY_USER = 'rest.authorizations.stamp';

    #[\Override]
    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    #[Test]
    public function it_evaluates_the_decisions_of_a_forgotten_user_again(): void
    {
        [$first, $second] = [new GenericUser(['id' => 1]), new GenericUser(['id' => 2])];

        $this->searchAs($first, $second, $first, $second);
        Rest::forgetAuthorizationsFor($first);
        $this->searchAs($first, $second);

        $this->assertSame(['viewAny', 'viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'session', 'provider' => 'users'])]
    public function it_forgets_a_user_on_every_guard(): void
    {
        $user = User::factory()->make(['id' => 1]);

        foreach (['web', 'api', 'web', 'api'] as $guard) {
            $this->actingAs($user, $guard)
                ->postJson('/api/cached-tickets/search')
                ->assertOk();
        }

        Rest::forgetAuthorizationsFor($user);

        foreach (['web', 'api'] as $guard) {
            $this->actingAs($user, $guard)
                ->postJson('/api/cached-tickets/search')
                ->assertOk();
        }

        $this->assertSame(['viewAny', 'viewAny', 'viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_forgets_the_decisions_of_every_user_and_of_guests(): void
    {
        $user = new GenericUser(['id' => 1]);

        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->searchAs($user, $user);
        Rest::forgetAuthorizations();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/cached-tickets/search')->assertOk();
        $this->searchAs($user);

        $this->assertSame(['viewAny', 'viewAny', 'viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_follows_the_policy_once_a_user_that_a_before_callback_allowed_is_forgotten(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        Gate::before(fn (?Authenticatable $user): ?bool => $user instanceof GenericUser && $user->admin === true ? true : null);

        $answers = [];

        foreach ([true, false, false] as $index => $admin) {
            if ($index === 2) {
                Rest::forgetAuthorizationsFor(new GenericUser(['id' => 2]));
            }

            $answers[] = $this->actingAs(new GenericUser(['id' => 2, 'admin' => $admin]))
                ->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['delete']]])
                ->assertOk()
                ->json('data.0.gates.authorized_to_delete');
        }

        $this->assertSame([
            ['allowed' => true, 'message' => null],
            ['allowed' => true, 'message' => null],
            ['allowed' => false, 'message' => 'Only the helpdesk lead deletes tickets.'],
        ], $answers);
        $this->assertSame(['viewAny', ...$this->calls('delete', $laptop, $toner, $reset, $jam)], $this->tickets->calls);
    }

    #[Test]
    public function it_renews_the_stamp_of_a_user_while_the_cache_is_off(): void
    {
        $user = new GenericUser(['id' => 1]);

        $this->searchAs($user);
        config(['rest.authorizations.cache.enabled' => false]);
        Rest::forgetAuthorizationsFor($user);
        config(['rest.authorizations.cache.enabled' => true]);
        $this->searchAs($user);

        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_creates_a_new_stamp_when_the_store_loses_one(): void
    {
        $user = new GenericUser(['id' => 1]);
        $stamp = self::EVERY_USER.'.'.hash('sha256', GenericUser::class.'#1');

        $this->searchAs($user, $user);
        $before = Cache::many([self::EVERY_USER, $stamp]);
        Cache::forget($stamp);
        $this->searchAs($user, $user);
        $after = Cache::many([self::EVERY_USER, $stamp]);

        $this->assertSame([16, 16, $before[self::EVERY_USER]], [strlen($before[$stamp]), strlen($after[$stamp]), $after[self::EVERY_USER]]);
        $this->assertNotSame($before[$stamp], $after[$stamp]);
        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_reads_the_stamps_once_per_request_and_only_for_a_declared_ability(): void
    {
        $this->seedTickets();
        $this->travel(1)->seconds();
        $reads = [];
        $writes = [];
        Event::listen(RetrievingManyKeys::class, function (RetrievingManyKeys $event) use (&$reads): void {
            $reads[] = count($event->keys) === 1 ? 'decision' : 'stamps';
        });
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes): void {
            $writes[] = $event->seconds === null ? 'stamp' : 'decision';
        });

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['view']]])->assertOk();
        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['view']]])->assertOk();
        $cold = [$reads, $writes];
        [$reads, $writes] = [[], []];
        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['view']]])->assertOk();

        $this->assertSame([
            [['stamps', 'decision', 'decision', 'decision', 'decision', 'decision'], ['stamp', 'stamp', 'decision', 'decision', 'decision', 'decision', 'decision']],
            [['stamps', 'decision', 'decision', 'decision', 'decision', 'decision'], []],
        ], [$cold, [$reads, $writes]]);
    }

    #[Test]
    public function it_renews_the_stamp_in_the_store_of_the_config(): void
    {
        $directory = sys_get_temp_dir().'/lomkit-rest-stamps';
        (new Filesystem)->deleteDirectory($directory);
        config(['rest.authorizations.cache.store' => 'file', 'cache.stores.file.path' => $directory]);
        Str::createRandomStringsUsingSequence(['user-1']);

        Rest::forgetAuthorizationsFor(new GenericUser(['id' => 1]));

        $this->assertSame('user-1', Cache::store('file')->get(self::EVERY_USER.'.'.hash('sha256', GenericUser::class.'#1')));
        $this->assertNull(Cache::store()->get(self::EVERY_USER.'.'.hash('sha256', GenericUser::class.'#1')));
    }

    private function searchAs(Authenticatable ...$users): void
    {
        foreach ($users as $user) {
            $this->actingAs($user)
                ->postJson('/api/cached-tickets/search')
                ->assertOk();
        }
    }
}
