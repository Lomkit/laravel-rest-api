<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Authorization;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TeamTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TeamTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DottedContextTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DottedContextTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstLateCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondEarlyCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TenantCacheContext;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class CacheContextExtensionsTest extends HelpdeskTestCase
{
    private const array SEARCH = ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]];

    /** @var list<int> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::reset();
        Rest::resource('team-tickets', TeamTicketController::class)->register();
        Rest::resource('dotted-context-tickets', DottedContextTicketController::class)->register();
        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            if ($event->seconds !== null) {
                $this->written[] = $event->seconds;
            }
        });
        $this->travelTo('2026-11-06 10:00:00');
        Team::forceCreate(['id' => 1, 'name' => 'Billing']);
        Ticket::forceCreate(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Ticket::forceCreate(['id' => 2, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'open']);
        $this->travelTo('2026-11-06 10:00:05');
    }

    #[\Override]
    protected function tearDown(): void
    {
        self::reset();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('stores')]
    public function it_keeps_the_decisions_of_two_tenants_apart(string $store): void
    {
        $this->useStore($store);
        config(['rest.extensions' => [TenantCacheContext::class]]);
        $tenant = 0;
        FirstCacheContext::$contribute = static function (CacheContributionContext $context) use (&$tenant): void {
            $context->put('id', $tenant);
        };

        foreach ([1, 2, 1, 2] as $tenant) {
            $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        }

        $this->assertSame(['viewAny', 'update 1', 'update 2', 'viewAny', 'update 1', 'update 2'], $this->tickets->calls);
        $this->assertDatabaseCount('cache', $store === 'database' ? 7 : 0);
    }

    #[Test]
    #[DataProvider('stores')]
    public function it_never_serves_a_decision_after_the_rules_fingerprint_changes(string $store): void
    {
        $this->useStore($store);
        config(['rest.extensions' => [FirstCacheContext::class]]);
        $rules = '';
        FirstCacheContext::$contribute = static function (CacheContributionContext $context) use (&$rules): void {
            $context->put('rules', $rules);
        };

        foreach (['v1', 'v2', 'v2', 'v1'] as $rules) {
            $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        }

        $this->assertSame(['viewAny', 'update 1', 'update 2', 'viewAny', 'update 1', 'update 2'], $this->tickets->calls);
        $this->assertDatabaseCount('cache', $store === 'database' ? 7 : 0);
    }

    /** @return iterable<string, array{string}> */
    public static function stores(): iterable
    {
        yield 'the array store' => ['array'];
        yield 'the database store' => ['database'];
    }

    #[Test]
    public function it_keys_each_item_under_its_extension_name(): void
    {
        config(['rest.extensions' => [SecondCacheContext::class, FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('rules', 'v1');
        SecondCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('id', 7);
        $definition = $this->contextFor(new GenericUser(['id' => 1, 'current_team_id' => 3]))->resource(TeamTicketResource::class)->definition();

        $this->assertSame(['acl.rules' => 'v1', 'team' => 3, 'tenant.id' => 7], $definition->cacheContext()->values);
        $this->assertSame(serialize(['acl.rules' => 'v1', 'team' => 3, 'tenant.id' => 7]), $definition->cachedAbilities()->context);
    }

    /** @param list<class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('itemlessExtensions')]
    public function it_keeps_the_keys_of_a_resource_without_an_item(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $definition = $this->contextFor(new GenericUser(['id' => 1, 'current_team_id' => 3]))->resource(TeamTicketResource::class)->definition();

        $this->assertSame('a:1:{s:4:"team";i:3;}', $definition->cachedAbilities()->context);
        $this->assertSame('a:1:{s:4:"team";i:3;}', $definition->cacheContext()->serialized());
    }

    /** @return iterable<string, array{list<class-string<Extension>>}> */
    public static function itemlessExtensions(): iterable
    {
        yield 'no extension' => [[]];
        yield 'an extension that puts nothing' => [[FirstCacheContext::class]];
    }

    #[Test]
    public function it_shortens_the_cache_of_an_ability(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->shorten('update', 30);

        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        $this->travel(31)->seconds();
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame([300, 30, 30, 30, 30], $this->written);
        $this->assertSame(['viewAny', 'update 1', 'update 2', 'update 1', 'update 2'], $this->tickets->calls);
    }

    #[Test]
    public function it_never_lengthens_the_cache_of_an_ability(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->shorten('update', 3600);

        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        $this->travel(301)->seconds();
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame([300, 300, 300, 300, 300, 300], $this->written);
        $this->assertSame(['viewAny', 'update 1', 'update 2', 'viewAny', 'update 1', 'update 2'], $this->tickets->calls);
    }

    /** @param list<class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('configOrders')]
    public function it_keeps_the_shortest_duration_and_the_same_items_in_any_extension_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        FirstCacheContext::$contribute = static function (CacheContributionContext $context): void {
            $context->shorten('update', 120);
            $context->put('tenant', 1);
        };
        SecondCacheContext::$contribute = static function (CacheContributionContext $context): void {
            $context->shorten('update', 30);
            $context->put('rules', 'v1');
        };

        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame([300, 30, 30], $this->written);
        $definition = $this->contextFor(null)->resource(CachedTicketResource::class)->definition();
        $this->assertSame(serialize(['a.rules' => 'v1', 'b.tenant' => 1]), $definition->cacheContext()->serialized());
        $this->assertSame(30, $definition->cachedAbilities()->seconds('update'));
    }

    /** @return iterable<string, array{list<class-string<Extension>>}> */
    public static function configOrders(): iterable
    {
        yield 'b before a' => [[FirstLateCacheContext::class, SecondEarlyCacheContext::class]];
        yield 'a before b' => [[SecondEarlyCacheContext::class, FirstLateCacheContext::class]];
    }

    #[Test]
    public function it_forgets_the_cache_of_an_ability(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static function (CacheContributionContext $context): void {
            $context->forget('update');
            $context->shorten('update', 30);
        };

        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame([300], $this->written);
        $this->assertSame(['viewAny', 'update 1', 'update 2', 'update 1', 'update 2'], $this->tickets->calls);
    }

    #[Test]
    public function it_never_adds_an_ability_to_the_cache(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->shorten('escalate', 30);
        $search = ['search' => ['gates' => ['escalate'], 'sorts' => [['field' => 'id']]]];

        $this->postJson('/api/cached-tickets/search', $search)->assertOk();
        $this->postJson('/api/cached-tickets/search', $search)->assertOk();

        $this->assertSame([300], $this->written);
        $this->assertSame(['viewAny', 'escalate 1', 'escalate 2', 'escalate 1', 'escalate 2'], $this->tickets->calls);
        $this->assertNull($this->contextFor(null)->resource(CachedTicketResource::class)->definition()->cachedAbilities()->seconds('escalate'));
    }

    #[Test]
    public function it_calls_no_extension_for_a_resource_that_keeps_nothing(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->shorten('update', 30);

        $this->postJson('/api/tickets/search', self::SEARCH)->assertOk();
        $this->postJson('/api/tickets/search', self::SEARCH)->assertOk();

        $this->assertSame([], FirstCacheContext::$contexts);
        $this->assertSame([], $this->written);
    }

    #[Test]
    public function it_calls_the_extension_once_per_request(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);

        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['update', 'delete'], 'includes' => [['relation' => 'team']]]])->assertOk();

        $this->assertCount(1, FirstCacheContext::$contexts);
    }

    #[Test]
    public function it_gives_the_extension_the_resource_and_the_acting_user(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        $lead = new GenericUser(['id' => TicketPolicy::LEAD]);
        $other = new GenericUser(['id' => 9]);

        $this->actingAs($lead)->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        $this->actingAs(new GenericUser(['id' => 7]));
        Rest::search(CachedTicketResource::class)->as($other)->payload(['gates' => ['update']])->render();

        $this->assertCount(2, FirstCacheContext::$contexts);
        $this->assertSame($lead, FirstCacheContext::$contexts[0]->user);
        $this->assertInstanceOf(CachedTicketResource::class, FirstCacheContext::$contexts[0]->resource);
        $this->assertSame($other, FirstCacheContext::$contexts[1]->user);
    }

    /** @param Closure(CacheContributionContext): void $contribute */
    #[Test]
    #[DataProvider('invalidContributions')]
    public function it_names_the_extension_of_an_invalid_contribution(Closure $contribute, InvalidAuthorizationExtensionException $expected): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = $contribute;
        $this->withoutExceptionHandling();

        $this->expectExceptionObject($expected);

        $this->postJson('/api/cached-tickets/search', self::SEARCH);
    }

    /** @return iterable<string, array{Closure(CacheContributionContext): void, InvalidAuthorizationExtensionException}> */
    public static function invalidContributions(): iterable
    {
        yield 'a key with a dot' => [
            static fn (CacheContributionContext $context) => $context->put('tenant.id', 1),
            InvalidAuthorizationExtensionException::invalidContextKey('acl', CachedTicketResource::class, 'tenant.id'),
        ];
        yield 'a key of 65 characters' => [
            static fn (CacheContributionContext $context) => $context->put(str_repeat('a', 65), 1),
            InvalidAuthorizationExtensionException::invalidContextKey('acl', CachedTicketResource::class, str_repeat('a', 65)),
        ];
        yield 'a float that is not finite' => [
            static fn (CacheContributionContext $context) => $context->put('weight', INF),
            InvalidAuthorizationExtensionException::invalidContextValue('acl', CachedTicketResource::class, 'weight'),
        ];
        yield 'a duration of zero' => [
            static fn (CacheContributionContext $context) => $context->shorten('update', 0),
            InvalidAuthorizationExtensionException::invalidCacheDuration('acl', CachedTicketResource::class, 'update', 0),
        ];
    }

    #[Test]
    public function it_refuses_a_key_that_the_resource_returns(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('rules', 'v1');
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::contextKeyTaken('acl', DottedContextTicketResource::class, 'acl.rules'));

        $this->postJson('/dotted-context-tickets/search', self::SEARCH);
    }

    #[Test]
    public function it_accepts_a_float(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('weight', 1.5);

        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();
        $this->postJson('/api/cached-tickets/search', self::SEARCH)->assertOk();

        $this->assertSame(['viewAny', 'update 1', 'update 2'], $this->tickets->calls);
        $this->assertSame(['acl.weight' => 1.5], $this->contextFor(null)->resource(CachedTicketResource::class)->definition()->cacheContext()->values);
    }

    private function useStore(string $store): void
    {
        config(['rest.authorizations.cache.store' => $store, 'cache.stores.database.connection' => null]);
    }

    #[Test]
    public function it_reads_the_cache_context_again_when_the_user_changes(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('team', $context->user instanceof GenericUser ? $context->user->current_team_id : null);
        $user = new GenericUser(['id' => 1, 'current_team_id' => 3]);
        $definition = $this->contextFollowing($user)->resource(CachedTicketResource::class)->definition();

        $first = [$definition->cacheContext()->values, $definition->cachedAbilities()->context];
        $user = new GenericUser(['id' => 2, 'current_team_id' => 4]);
        $second = [$definition->cacheContext()->values, $definition->cachedAbilities()->context];

        $this->assertSame([['acl.team' => 3], serialize(['acl.team' => 3])], $first);
        $this->assertSame([['acl.team' => 4], serialize(['acl.team' => 4])], $second);
    }

    #[Test]
    public function it_narrows_the_cached_abilities_for_the_new_user(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static function (CacheContributionContext $context): void {
            if (($context->user instanceof GenericUser ? $context->user->current_team_id : null) === 4) {
                $context->forget('view');
            }
        };
        $user = new GenericUser(['id' => 1, 'current_team_id' => 3]);
        $definition = $this->contextFollowing($user)->resource(CachedTicketResource::class)->definition();

        $first = $definition->cachedAbilities()->seconds('view');
        $user = new GenericUser(['id' => 2, 'current_team_id' => 4]);

        $this->assertSame(60, $first);
        $this->assertNull($definition->cachedAbilities()->seconds('view'));
    }

    #[Test]
    public function it_resolves_no_user_for_the_cached_abilities_without_a_context_extension(): void
    {
        $resolutions = 0;
        $definition = $this->countingContext($resolutions)->resource(CachedTicketResource::class)->definition();

        for ($read = 0; $read < 50; $read++) {
            $definition->cachedAbilities()->seconds('view');
            $definition->cacheContext();
        }

        $this->assertSame(0, $resolutions);
    }

    /** @param list<string> $abilities */
    #[Test]
    #[DataProvider('checkedAbilities')]
    public function it_resolves_the_user_as_often_for_a_batch_of_many_checks_as_for_one(array $abilities): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('rules', 'v1');

        $this->assertSame($this->resolutionsFor(['view']), $this->resolutionsFor($abilities));
    }

    /** @return iterable<string, array{list<string>}> */
    public static function checkedAbilities(): iterable
    {
        yield 'one ability' => [['update']];
        yield 'three abilities' => [['view', 'update', 'delete']];
    }

    #[Test]
    public function it_keeps_the_cache_context_while_the_user_stays(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('rules', 'v1');
        $user = new GenericUser(['id' => 1, 'current_team_id' => 3]);
        $definition = $this->contextFollowing($user)->resource(CachedTicketResource::class)->definition();

        $definition->cacheContext();
        $definition->cachedAbilities()->seconds('view');
        $definition->cacheContext();

        $this->assertCount(1, FirstCacheContext::$contexts);
    }

    /** @param list<string> $abilities */
    private function resolutionsFor(array $abilities): int
    {
        $resolutions = 0;
        $context = $this->countingContext($resolutions);
        $resource = $context->resource(CachedTicketResource::class);
        $checks = [];

        foreach ($abilities as $ability) {
            foreach (Ticket::query()->orderBy('id')->get()->all() as $ticket) {
                $checks[] = new Check($resource, $ability, $ticket);
            }
        }

        $context->authorizer()->inspectAll($checks);

        return $resolutions;
    }

    private function countingContext(int &$resolutions): RestContext
    {
        $request = RestRequest::create('/api/cached-tickets/search', 'POST');
        $request->setUserResolver(static function () use (&$resolutions): Authenticatable {
            $resolutions++;

            return new GenericUser(['id' => 1, 'current_team_id' => 3]);
        });

        return new RestContext($request, $this->app ?? $this->fail('No application.'));
    }

    private function contextFollowing(?Authenticatable &$user): RestContext
    {
        $request = RestRequest::create('/api/cached-tickets/search', 'POST');
        $request->setUserResolver(static function () use (&$user): ?Authenticatable {
            return $user;
        });

        return new RestContext($request, $this->app ?? $this->fail('No application.'));
    }

    private static function reset(): void
    {
        FirstCacheContext::reset();
        SecondCacheContext::reset();
    }
}
