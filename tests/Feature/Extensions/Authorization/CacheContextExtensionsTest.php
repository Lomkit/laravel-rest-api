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
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
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
    use ResetsExtensionStubs;

    private const array SEARCH = ['search' => ['gates' => ['update'], 'sorts' => [['field' => 'id']]]];

    /** @var list<int> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * @param  Closure(CacheContributionContext): mixed  $contribute
     * @param  list<int>  $written
     * @param  list<string>  $calls
     */
    #[Test]
    #[DataProvider('cacheChanges')]
    public function it_only_shortens_or_forgets_the_cache_of_a_cached_ability(Closure $contribute, string $gate, int $later, array $written, array $calls, ?int $seconds): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = $contribute;
        $search = ['search' => ['gates' => [$gate], 'sorts' => [['field' => 'id']]]];

        $this->postJson('/api/cached-tickets/search', $search)->assertOk();
        $this->travel($later)->seconds();
        $this->postJson('/api/cached-tickets/search', $search)->assertOk();

        $this->assertSame($written, $this->written);
        $this->assertSame($calls, $this->tickets->calls);
        $this->assertSame($seconds, $this->contextFor(null)->resource(CachedTicketResource::class)->definition()->cachedAbilities()->seconds($gate));
    }

    /** @return iterable<string, array{Closure(CacheContributionContext): mixed, string, int, list<int>, list<string>, ?int}> */
    public static function cacheChanges(): iterable
    {
        yield 'a shorter duration' => [
            static fn (CacheContributionContext $context) => $context->shorten('update', 30),
            'update',
            31,
            [300, 30, 30, 30, 30],
            ['viewAny', 'update 1', 'update 2', 'update 1', 'update 2'],
            30,
        ];
        yield 'a longer duration' => [
            static fn (CacheContributionContext $context) => $context->shorten('update', 3600),
            'update',
            301,
            [300, 300, 300, 300, 300, 300],
            ['viewAny', 'update 1', 'update 2', 'viewAny', 'update 1', 'update 2'],
            300,
        ];
        yield 'a forgotten ability' => [
            static function (CacheContributionContext $context): void {
                $context->forget('update');
                $context->shorten('update', 30);
            },
            'update',
            0,
            [300],
            ['viewAny', 'update 1', 'update 2', 'update 1', 'update 2'],
            null,
        ];
        yield 'an ability the resource does not cache' => [
            static fn (CacheContributionContext $context) => $context->shorten('escalate', 30),
            'escalate',
            0,
            [300],
            ['viewAny', 'escalate 1', 'escalate 2', 'escalate 1', 'escalate 2'],
            null,
        ];
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
    public function it_calls_the_extension_once_per_request_and_once_while_the_user_stays(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static fn (CacheContributionContext $context) => $context->put('rules', 'v1');

        $this->postJson('/api/cached-tickets/search', ['search' => ['gates' => ['update', 'delete'], 'includes' => [['relation' => 'team']]]])->assertOk();

        $this->assertCount(1, FirstCacheContext::$contexts);
        $user = new GenericUser(['id' => 1, 'current_team_id' => 3]);
        $definition = $this->contextFollowing($user)->resource(CachedTicketResource::class)->definition();
        $definition->cacheContext();
        $definition->cachedAbilities()->seconds('view');
        $definition->cacheContext();
        $this->assertCount(2, FirstCacheContext::$contexts);
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

    /**
     * @param  Closure(CacheContributionContext): void  $contribute
     * @param  Closure(): InvalidAuthorizationExtensionException  $expected
     */
    #[Test]
    #[DataProvider('refusedContributions')]
    public function it_names_the_registered_class_of_a_cache_context_extension_bound_to_a_subclass(string $uri, Closure $contribute, Closure $expected): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        $this->app?->bind(FirstCacheContext::class, static fn (): FirstCacheContext => new class extends FirstCacheContext {});
        FirstCacheContext::$contribute = $contribute;
        $this->withoutExceptionHandling();

        try {
            $this->postJson($uri, self::SEARCH);
            $this->fail('The contribution was accepted.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertSame($expected()->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(FirstCacheContext::class));
        }
    }

    /** @return iterable<string, array{string, Closure(CacheContributionContext): void, Closure(): InvalidAuthorizationExtensionException}> */
    public static function refusedContributions(): iterable
    {
        yield 'a duration of zero' => [
            '/api/cached-tickets/search',
            static fn (CacheContributionContext $context) => $context->shorten('update', 0),
            static fn (): InvalidAuthorizationExtensionException => InvalidAuthorizationExtensionException::invalidCacheDuration('acl', FirstCacheContext::class, CachedTicketResource::class, 'update', 0),
        ];
        yield 'a key that the resource returns' => [
            '/dotted-context-tickets/search',
            static fn (CacheContributionContext $context) => $context->put('rules', 'v1'),
            static fn (): InvalidAuthorizationExtensionException => InvalidAuthorizationExtensionException::contextKeyTaken('acl', FirstCacheContext::class, DottedContextTicketResource::class, 'acl.rules'),
        ];
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
    public function it_reads_the_cache_context_and_the_cached_abilities_again_when_the_user_changes(): void
    {
        config(['rest.extensions' => [FirstCacheContext::class]]);
        FirstCacheContext::$contribute = static function (CacheContributionContext $context): void {
            $team = $context->user instanceof GenericUser ? $context->user->current_team_id : null;
            $context->put('team', $team);

            if ($team === 4) {
                $context->forget('view');
            }
        };
        $user = new GenericUser(['id' => 1, 'current_team_id' => 3]);
        $definition = $this->contextFollowing($user)->resource(CachedTicketResource::class)->definition();

        $first = [$definition->cacheContext()->values, $definition->cachedAbilities()->context, $definition->cachedAbilities()->seconds('view')];
        $user = new GenericUser(['id' => 2, 'current_team_id' => 4]);
        $second = [$definition->cacheContext()->values, $definition->cachedAbilities()->context, $definition->cachedAbilities()->seconds('view')];

        $this->assertSame([['acl.team' => 3], serialize(['acl.team' => 3]), 60], $first);
        $this->assertSame([['acl.team' => 4], serialize(['acl.team' => 4]), null], $second);
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
}
