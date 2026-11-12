<?php

namespace Lomkit\Rest\Tests\Feature\Metrics;

use Closure;
use DateInterval;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Metrics\CacheScope;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricOrderResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrderCount;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\OrdersByStatus;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\PerimeterOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\SharedOrderCount;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TeamOrderController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TotalRevenue;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\WeeklyOrders;
use Lomkit\Rest\Tests\Fixtures\Sales\Order;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class MetricCacheTest extends SalesTestCase
{
    private const string TEAM_ONE = '{"data":{"type":"value","value":52.0,"previous":37.25}}';

    private const string TEAM_TWO = '{"data":{"type":"value","value":500.0,"previous":0.0}}';

    private const string SEVEN = '{"data":{"type":"value","value":7,"previous":null}}';

    private const string ONE = '{"data":{"type":"value","value":1,"previous":null}}';

    private const string BY_STATUS = '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4},{"key":"pending","label":"Pending","value":1},{"key":"refunded","label":"Refunded","value":1},{"key":null,"label":null,"value":1}],"other":null}}';

    /** @var list<string> */
    private array $calls = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Event::listen([RetrievingKey::class, RetrievingManyKeys::class, WritingKey::class, WritingManyKeys::class], function (CacheEvent $event): void {
            $this->calls[] = self::describe($event);
        });
    }

    #[Test]
    public function it_serves_a_second_request_from_the_default_store(): void
    {
        $this->assertSecondRequestServedFromTheStore();
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_serves_a_second_request_from_the_file_store(): void
    {
        $this->assertSecondRequestServedFromTheStore();
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.store', 'database')]
    #[WithConfig('cache.stores.database.connection', null)]
    public function it_serves_a_second_request_from_the_database_store(): void
    {
        $this->assertSecondRequestServedFromTheStore();
        $this->assertDatabaseCount('cache', 3);
    }

    #[Test]
    #[DataProvider('identities')]
    public function it_keys_a_result_by_its_stamps_its_user_its_request_and_its_context(?Authenticatable $user, CacheScope $scope, string $identity): void
    {
        Cache::forever('rest.authorizations.stamp', 'every-user');
        Cache::forever('rest.authorizations.stamp.'.hash('sha256', 'Illuminate\Auth\GenericUser#1'), 'user-1');
        $this->serveTeams(TotalRevenue::make()->cacheFor(600)->cacheScope($scope));
        $keys = [];
        Event::listen(WritingKey::class, function (WritingKey $event) use (&$keys): void {
            if (str_starts_with($event->key, 'rest.metrics.')) {
                $keys[] = $event->key;
            }
        });
        $request = $user === null ? $this : $this->actingAs($user);

        $request->postJson('/team-orders/metrics/total-revenue', ['search' => ['filters' => [['value' => 'paid', 'field' => 'status']]]])->assertOk();

        $team = $user === null ? 'N;' : 'i:1;';
        $perimeter = $scope === CacheScope::Global ? ',"'.$this->perimeterOf(1).'"' : '';
        $parts = '["Lomkit\\\\Rest\\\\Tests\\\\Feature\\\\Metrics\\\\Stubs\\\\TeamOrderResource","Lomkit\\\\Rest\\\\Tests\\\\Feature\\\\Metrics\\\\Stubs\\\\TotalRevenue","total-revenue",{"cacheScope":"'.$scope->value.'","name":null,"uriKey":null,"weekStartsOn":1,"withoutSearching":false},30,"UTC",{"filters":[{"field":"status","value":"paid"}]},"a:1:{s:4:\"team\";'.$team.'}"'.$perimeter.']';
        $this->assertSame(['rest.metrics.'.hash('sha256', $identity.$parts)], $keys);
    }

    /** @return iterable<string, array{?Authenticatable, CacheScope, string}> */
    public static function identities(): iterable
    {
        $user = new GenericUser(['id' => 1, 'team_id' => 1]);

        yield 'a user' => [$user, CacheScope::User, 'every-user|user-1|web|Illuminate\Auth\GenericUser#1|'];
        yield 'a guest' => [null, CacheScope::User, 'every-user|web|guest|'];
        yield 'every user' => [$user, CacheScope::Global, 'every-user|global|'];
    }

    #[Test]
    public function it_keeps_the_result_of_each_user_apart(): void
    {
        $this->serveTeams(TotalRevenue::make()->cacheFor(600));
        $users = [new GenericUser(['id' => 1, 'team_id' => 1]), new GenericUser(['id' => 2, 'team_id' => 1])];

        $this->assertSame([2, 0], [$this->statementsOf($users, 'total-revenue'), $this->statementsOf($users, 'total-revenue')]);
    }

    #[Test]
    public function it_keeps_the_result_of_each_context_apart(): void
    {
        $this->serveTeams(TotalRevenue::make()->cacheFor(600));
        $contents = [];
        $this->logQueries();

        foreach ([1, 2, 1, 2] as $team) {
            $contents[] = $this->metricAs(new GenericUser(['id' => 1, 'team_id' => $team]), 'total-revenue')->getContent();
        }

        $this->assertSame([self::TEAM_ONE, self::TEAM_TWO, self::TEAM_ONE, self::TEAM_TWO], $contents);
        $this->assertSame(2, $this->orderStatements());
    }

    #[Test]
    #[DataProvider('scopes')]
    public function it_shares_a_global_result_between_users(Closure $declare, string $key): void
    {
        $this->serveTeams($declare());
        $users = [new GenericUser(['id' => 1, 'team_id' => 1]), new GenericUser(['id' => 2, 'team_id' => 1])];

        $this->assertSame(1, $this->statementsOf($users, $key));
        $this->assertSame(self::SEVEN, $this->metricAs($users[1], $key)->getContent());
    }

    /** @return iterable<string, array{Closure(): Metric, string}> */
    public static function scopes(): iterable
    {
        yield 'declared' => [fn (): Metric => OrderCount::make()->cacheFor(300)->cacheScope('global'), 'order-count'];
        yield 'declared with the enum' => [fn (): Metric => OrderCount::make()->cacheFor(300)->cacheScope(CacheScope::Global), 'order-count'];
        yield 'set in the class' => [fn (): Metric => SharedOrderCount::make(), 'shared-order-count'];
    }

    #[Test]
    public function it_keeps_a_global_result_of_each_context_apart(): void
    {
        $this->serveTeams(SharedOrderCount::make());

        $first = $this->metricAs(new GenericUser(['id' => 1, 'team_id' => 1]), 'shared-order-count')->getContent();
        $second = $this->metricAs(new GenericUser(['id' => 2, 'team_id' => 2]), 'shared-order-count')->getContent();

        $this->assertSame([self::SEVEN, self::ONE], [$first, $second]);
    }

    #[Test]
    public function it_keeps_a_global_result_of_each_perimeter_apart(): void
    {
        $this->servePerimeters(SharedOrderCount::make());
        $keys = [];
        Event::listen(RetrievingKey::class, function (RetrievingKey $event) use (&$keys): void {
            if (str_starts_with($event->key, 'rest.metrics.')) {
                $keys[] = $event->key;
            }
        });
        $this->logQueries();
        $contents = [];
        $calls = [];

        foreach ([[1, 1], [3, 2], [2, 1], [4, 2]] as [$id, $team]) {
            $this->calls = [];
            $contents[] = $this->metricAs(new GenericUser(['id' => $id, 'team_id' => $team]), 'shared-order-count')->getContent();
            $calls[] = $this->calls;
        }

        $this->assertSame([self::SEVEN, self::ONE, self::SEVEN, self::ONE], $contents);
        $this->assertSame(2, $this->orderStatements());
        $this->assertSame([$keys[0], $keys[1], $keys[0], $keys[1]], $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame([
            ['read 1 keys', 'write a stamp', 'read a result', 'write a result'],
            ['read 1 keys', 'read a result', 'write a result'],
            ['read 1 keys', 'read a result'],
            ['read 1 keys', 'read a result'],
        ], $calls);
    }

    #[Test]
    public function it_computes_again_the_global_results_of_each_perimeter_once_forgotten(): void
    {
        $this->servePerimeters(SharedOrderCount::make());
        $users = [new GenericUser(['id' => 1, 'team_id' => 1]), new GenericUser(['id' => 3, 'team_id' => 2])];
        $statements = [$this->statementsOf($users, 'shared-order-count')];

        Rest::forgetAuthorizationsFor($users[0]);
        $statements[] = $this->statementsOf($users, 'shared-order-count');

        Rest::forgetAuthorizations();
        $statements[] = $this->statementsOf($users, 'shared-order-count');

        $this->assertSame([2, 0, 2], $statements);
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.enabled', false)]
    public function it_keeps_no_global_result_of_a_perimeter_when_the_cache_is_off(): void
    {
        $this->servePerimeters(SharedOrderCount::make());
        $contents = [];
        $this->logQueries();

        foreach ([[1, 1], [3, 2], [2, 1]] as [$id, $team]) {
            $contents[] = $this->metricAs(new GenericUser(['id' => $id, 'team_id' => $team]), 'shared-order-count')->getContent();
        }

        $this->assertSame([self::SEVEN, self::ONE, self::SEVEN], $contents);
        $this->assertSame(3, $this->orderStatements());
        $this->assertSame([], $this->calls);
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    #[Test]
    #[DataProvider('bodies')]
    public function it_keeps_the_result_of_each_request_apart(array $first, array $second, int $statements): void
    {
        $this->serve(TotalRevenue::make()->cacheFor(600));

        $this->logQueries();
        $this->metric('total-revenue', $first)->assertOk();
        $this->metric('total-revenue', $second)->assertOk();

        $this->assertSame($statements, $this->orderStatements());
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>, int}> */
    public static function bodies(): iterable
    {
        $paid = ['field' => 'status', 'value' => 'paid'];
        $web = ['field' => 'channel', 'value' => 'web'];

        yield 'the same body' => [['range' => 90], ['range' => 90], 1];
        yield 'another range' => [[], ['range' => 90], 2];
        yield 'the first range, sent' => [[], ['range' => 30], 1];
        yield 'another time zone' => [[], ['timezone' => 'Europe/Paris'], 2];
        yield 'the app time zone, sent' => [[], ['timezone' => 'UTC'], 1];
        yield 'another filter' => [['search' => ['filters' => [$paid]]], ['search' => ['filters' => [$web]]], 2];
        yield 'the keys of a filter in another order' => [['search' => ['filters' => [$paid]]], ['search' => ['filters' => [['value' => 'paid', 'field' => 'status']]]], 1];
        yield 'the filters in another order' => [['search' => ['filters' => [$paid, $web]]], ['search' => ['filters' => [$web, $paid]]], 2];
        yield 'an empty search' => [[], ['search' => []], 1];
        yield 'a text' => [[], ['search' => ['text' => ['value' => 'A-100']]], 2];
    }

    #[Test]
    #[DataProvider('settings')]
    public function it_keeps_the_result_of_each_setting_apart(Closure $first, Closure $second, string $key, string $before, string $after, int $statements): void
    {
        $this->serve($first());
        $this->logQueries();
        $contents = [$this->metric($key)->getContent()];

        MetricOrderResource::$declared = [$second()];
        $contents[] = $this->metric($key)->getContent();

        $this->assertSame([$before, $after], $contents);
        $this->assertSame($statements, $this->orderStatements());
    }

    /** @return iterable<string, array{Closure(): Metric, Closure(): Metric, string, string, string, int}> */
    public static function settings(): iterable
    {
        yield 'a limit' => [
            fn (): Metric => OrdersByStatus::make()->cacheFor(600),
            fn (): Metric => OrdersByStatus::make()->cacheFor(600)->limit(1),
            'orders-by-status',
            self::BY_STATUS,
            '{"data":{"type":"partition","parts":[{"key":"paid","label":"Paid","value":4}],"other":{"label":"Other","value":3}}}',
            3,
        ];
        yield 'a week start' => [
            fn (): Metric => WeeklyOrders::make()->cacheFor(600),
            fn (): Metric => WeeklyOrders::make()->cacheFor(600)->weekStartsOn(0),
            'weekly-orders',
            '{"data":{"type":"trend","series":[{"label":"2026-10-26","value":1},{"label":"2026-11-02","value":0}]}}',
            '{"data":{"type":"trend","series":[{"label":"2026-10-25","value":0},{"label":"2026-11-01","value":1}]}}',
            2,
        ];
    }

    #[Test]
    public function it_computes_again_the_results_of_a_forgotten_user(): void
    {
        $this->serveTeams(TotalRevenue::make()->cacheFor(600), SharedOrderCount::make());
        $users = [new GenericUser(['id' => 1, 'team_id' => 1]), new GenericUser(['id' => 2, 'team_id' => 1])];
        $statements = [$this->statementsOf($users, 'total-revenue', 'shared-order-count')];

        Rest::forgetAuthorizationsFor($users[0]);
        $statements[] = $this->statementsOf($users, 'total-revenue', 'shared-order-count');

        Rest::forgetAuthorizations();
        $statements[] = $this->statementsOf($users, 'total-revenue', 'shared-order-count');

        $this->assertSame([3, 1, 3], $statements);
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.enabled', false)]
    public function it_keeps_no_result_when_the_cache_is_off(): void
    {
        $this->serve(TotalRevenue::make()->cacheFor(600));
        $this->logQueries();

        $this->assertSame([self::TEAM_ONE, self::TEAM_ONE], [$this->metric('total-revenue')->getContent(), $this->metric('total-revenue')->getContent()]);
        $this->assertSame(2, $this->orderStatements());
        $this->assertSame([], $this->calls);
    }

    #[Test]
    public function it_calls_no_store_for_a_metric_that_declares_no_cache(): void
    {
        $this->serve(TotalRevenue::make());
        $this->logQueries();

        $this->metric('total-revenue')->assertOk();
        $this->metric('total-revenue')->assertOk();

        $this->assertSame(2, $this->orderStatements());
        $this->assertSame([], $this->calls);
    }

    #[Test]
    #[DataProvider('durations')]
    public function it_keeps_a_result_for_its_duration(Closure $declare, int $seconds): void
    {
        $this->serve($declare());
        $written = [];
        Event::listen(WritingKey::class, function (WritingKey $event) use (&$written): void {
            if (str_starts_with($event->key, 'rest.metrics.')) {
                $written[] = $event->seconds;
            }
        });
        $this->logQueries();

        $this->metric('total-revenue')->assertOk();
        $this->travel($seconds - 1)->seconds();
        $this->metric('total-revenue')->assertOk();
        $this->travel(1)->seconds();
        $this->metric('total-revenue')->assertOk();

        $this->assertSame([$seconds, $seconds], $written);
        $this->assertSame(2, $this->orderStatements());
    }

    /** @return iterable<string, array{Closure(): Metric, int}> */
    public static function durations(): iterable
    {
        yield 'seconds' => [fn (): Metric => TotalRevenue::make()->cacheFor(600), 600];
        yield 'an interval' => [fn (): Metric => TotalRevenue::make()->cacheFor(new DateInterval('PT5M')), 300];
        yield 'an instant' => [fn (): Metric => TotalRevenue::make()->cacheFor(now()->addMinutes(10)), 600];
    }

    #[Test]
    public function it_keeps_a_result_until_an_instant(): void
    {
        $declare = fn (): Metric => TotalRevenue::make()->cacheFor(Date::parse('2026-11-07 12:10:00'));
        $this->serve($declare());
        $calls = [];
        $this->logQueries();

        foreach (['2026-11-07 12:00:00', '2026-11-07 12:09:59', '2026-11-07 12:10:00'] as $now) {
            $this->travelTo($now);
            MetricOrderResource::$declared = [$declare()];
            $this->calls = [];
            $this->metric('total-revenue')->assertOk();
            $calls[] = $this->calls;
        }

        $this->assertSame([['read 1 keys', 'write a stamp', 'read a result', 'write a result'], ['read 1 keys', 'read a result'], []], $calls);
        $this->assertSame(2, $this->orderStatements());
    }

    #[Test]
    public function it_refuses_a_search_value_that_is_not_valid_utf8_before_the_cache(): void
    {
        $this->serveTeams(OrderCount::make()->cacheFor(600));
        $body = ['search' => ['filters' => [['field' => 'customer', 'value' => "Q4\xFF"]]]];
        $this->logQueries();

        $response = $this->actingAs(new GenericUser(['id' => 1, 'team_id' => 1]))
            ->post('/team-orders/metrics/order-count', $body, ['Accept' => 'application/json']);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.filters.0.value field must be valid UTF-8.',
            'errors' => ['search.filters.0.value' => ['The search.filters.0.value field must be valid UTF-8.']],
        ]);
        $this->assertSame([], array_values(array_filter($this->calls, fn (string $call): bool => str_ends_with($call, 'a result'))));
        $this->assertSame(0, $this->orderStatements());
    }

    #[Test]
    public function it_still_validates_and_checks_a_request_whose_result_is_kept(): void
    {
        $this->serve(TotalRevenue::make()->cacheFor(600)->cacheScope('global'));
        $this->metric('total-revenue')->assertOk();
        Gate::before(fn (?Authenticatable $user): ?bool => $user?->getAuthIdentifier() === 2 ? false : null);

        $this->metric('total-revenue', ['timezone' => 'Mars/Base'])->assertUnprocessable();
        $this->actingAs(new GenericUser(['id' => 2]));
        $this->metric('total-revenue')->assertForbidden();
    }

    #[Test]
    #[DataProvider('invalidCaches')]
    public function it_refuses_a_cache_that_does_not_fit(Closure $declare, InvalidMetricException $exception): void
    {
        $this->expectExceptionObject($exception);

        $this->serve($declare());
    }

    /** @return iterable<string, array{Closure(): Metric, InvalidMetricException}> */
    public static function invalidCaches(): iterable
    {
        yield 'no second' => [fn (): Metric => TotalRevenue::make()->cacheFor(0), InvalidMetricException::invalidCacheDuration(TotalRevenue::class, 0)];
        yield 'seconds below zero' => [fn (): Metric => TotalRevenue::make()->cacheFor(-5), InvalidMetricException::invalidCacheDuration(TotalRevenue::class, -5)];
        yield 'an unknown scope' => [fn (): Metric => TotalRevenue::make()->cacheScope('team'), InvalidMetricException::invalidCacheScope(TotalRevenue::class, 'team')];
    }

    protected function useFileStore(Application $app): void
    {
        $directory = sys_get_temp_dir().'/lomkit-rest-metrics';
        (new Filesystem)->deleteDirectory($directory);

        $app['config']->set('rest.authorizations.cache.store', 'file');
        $app['config']->set('cache.stores.file.path', $directory);
    }

    private function assertSecondRequestServedFromTheStore(): void
    {
        $this->serveTeams(OrdersByStatus::make()->cacheFor(600));
        $user = new GenericUser(['id' => 1, 'team_id' => 1]);
        $this->logQueries();

        $first = $this->metricAs($user, 'orders-by-status')->getContent();
        $cold = $this->calls;
        $this->calls = [];
        $second = $this->metricAs($user, 'orders-by-status')->getContent();

        $this->assertSame([self::BY_STATUS, self::BY_STATUS], [$first, $second]);
        $this->assertSame(1, $this->orderStatements());
        $this->assertSame(['read 2 keys', 'write a stamp', 'write a stamp', 'read a result', 'write a result'], $cold);
        $this->assertSame(['read 2 keys', 'read a result'], $this->calls);
    }

    private function serveTeams(Metric ...$metrics): void
    {
        MetricOrderResource::$declared = array_values($metrics);
        Rest::resource('team-orders', TeamOrderController::class)->register();
    }

    private function servePerimeters(Metric ...$metrics): void
    {
        MetricOrderResource::$declared = array_values($metrics);
        Rest::resource('team-orders', PerimeterOrderController::class)->register();
    }

    private function perimeterOf(int $team): string
    {
        $query = Order::query()
            ->where(fn (Builder $query): Builder => $query->where('orders.team_id', $team))
            ->getQuery();

        return hash('sha256', serialize([$query->toSql(), $query->getBindings()]));
    }

    /** @param array<string, mixed> $body */
    private function metricAs(Authenticatable $user, string $key, array $body = []): TestResponse
    {
        return $this->actingAs($user)->postJson("/team-orders/metrics/{$key}", $body);
    }

    /** @param list<Authenticatable> $users */
    private function statementsOf(array $users, string ...$keys): int
    {
        $this->logQueries();

        foreach ($users as $user) {
            foreach ($keys as $key) {
                $this->metricAs($user, $key)->assertOk();
            }
        }

        return $this->orderStatements();
    }

    private static function describe(CacheEvent $event): string
    {
        if ($event instanceof RetrievingManyKeys || $event instanceof WritingManyKeys) {
            return ($event instanceof RetrievingManyKeys ? 'read ' : 'write ').count($event->keys).' keys';
        }

        return ($event instanceof RetrievingKey ? 'read ' : 'write ').(str_starts_with($event->key, 'rest.metrics.') ? 'a result' : 'a stamp');
    }

    private function orderStatements(): int
    {
        return count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'orders')));
    }
}
