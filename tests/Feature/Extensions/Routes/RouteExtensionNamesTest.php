<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Illuminate\Routing\Route;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\BadlyNamedStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RoutedStampNamedLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampNamedLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutesNamedA;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\RenamableExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class RouteExtensionNamesTest extends HelpdeskTestCase
{
    use ResetsExtensionStubs;

    private const string STAMP = StampsRequests::class.':a';

    #[Test]
    public function it_reads_no_name_of_an_entry_of_another_kind(): void
    {
        config(['rest.extensions' => [StampRoutes::class, RenamableExtension::class]]);
        RenamableExtension::$given = 'Not a name';

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->assertSame([], ExtensionLog::$named);
        $this->assertSame([], ExtensionLog::$built);
    }

    /**
     * @param  list<class-string>  $extensions
     * @param  list<class-string>  $disabled
     * @param  array{int, int, int, list<class-string>}  $built
     */
    #[Test]
    #[DataProvider('disabledEntries')]
    public function it_neither_builds_nor_names_a_disabled_route_extension(array $extensions, array $disabled, ?string $binding, string $stamp, array $built): void
    {
        config(['rest.extensions' => $extensions, 'rest.disabled_extensions' => $disabled]);

        if ($binding !== null) {
            $this->app->bind(StampNamedLimits::class, $binding);
        }

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, $stamp], $this->search()->middleware());
        $this->assertSame($built, [StampRoutes::$built, OtherStampRoutes::$built, RoutedStampNamedLimits::$built, StampRoutes::$ran]);
    }

    /** @return array<string, array{list<class-string>, list<class-string>, ?class-string, string, array{int, int, int, list<class-string>}}> */
    public static function disabledEntries(): array
    {
        return [
            'a class that shares its name with a kept one' => [[StampRoutes::class, OtherStampRoutes::class], [StampRoutes::class], null, StampsRequests::class.':b', [0, 1, 0, []]],
            'a class with an invalid name' => [[StampRoutes::class, BadlyNamedStampRoutes::class], [BadlyNamedStampRoutes::class], null, self::STAMP, [1, 0, 0, [StampRoutes::class]]],
            'a parent class but not its subclass' => [[StampRoutes::class, StampRoutesNamedA::class], [StampRoutes::class], null, self::STAMP, [1, 0, 0, [StampRoutesNamedA::class]]],
            'a class bound to a route subclass' => [[StampRoutes::class, StampNamedLimits::class], [StampNamedLimits::class], RoutedStampNamedLimits::class, self::STAMP, [1, 0, 0, [StampRoutes::class]]],
        ];
    }

    #[Test]
    public function it_refuses_a_name_that_a_route_class_shares_with_a_class_bound_to_a_route_subclass(): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampNamedLimits::class]]);
        $this->app->bind(StampNamedLimits::class, RoutedStampNamedLimits::class);

        $this->expectExceptionObject(InvalidExtensionException::sharedName('stamp', [StampRoutes::class, StampNamedLimits::class]));

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
    }

    #[Test]
    #[DataProvider('bindings')]
    public function it_leaves_a_name_shared_with_a_class_of_another_kind_to_the_request(bool $bound): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampNamedLimits::class]]);

        if ($bound) {
            $this->app->bind(StampNamedLimits::class);
        }

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->expectExceptionObject(InvalidExtensionException::sharedName('stamp', [StampRoutes::class, StampNamedLimits::class]));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }

    /** @return array<string, array{bool}> */
    public static function bindings(): array
    {
        return [
            'bound' => [true],
            'unbound' => [false],
        ];
    }

    #[Test]
    public function it_reads_no_name_of_a_bound_entry_of_another_kind(): void
    {
        config(['rest.extensions' => [StampRoutes::class, RenamableExtension::class]]);
        $this->app->bind(RenamableExtension::class);
        RenamableExtension::$given = 'Not a name';

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->assertSame([], ExtensionLog::$named);
    }

    #[Test]
    public function it_reads_the_name_of_a_bound_entry_that_adds_route_middleware(): void
    {
        config(['rest.extensions' => [StampNamedLimits::class]]);
        $this->app->bind(StampNamedLimits::class, RoutedStampNamedLimits::class);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':bound'], $this->search()->middleware());
        $this->assertSame(1, RoutedStampNamedLimits::$built);
    }

    #[Test]
    public function it_refuses_a_map_config_when_routes_register(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.extensions', 'a list of extension class names'));

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
    }

    /** @return array<string, array{list<string>, mixed, ?string}> */
    public static function keptRouteEntries(): array
    {
        return [
            'a route class beside a map' => [[StampRoutes::class], ['stamp' => StampRoutes::class], null],
            'a class bound to a route subclass beside a string' => [[StampNamedLimits::class], StampNamedLimits::class, RoutedStampNamedLimits::class],
        ];
    }

    /** @param list<string> $extensions */
    #[Test]
    #[DataProvider('keptRouteEntries')]
    public function it_refuses_a_disabled_config_that_is_not_a_list_when_routes_register(array $extensions, mixed $disabled, ?string $binding): void
    {
        config(['rest.extensions' => $extensions, 'rest.disabled_extensions' => $disabled]);

        if ($binding !== null) {
            $this->app->bind($extensions[0], $binding);
        }

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.disabled_extensions', 'a list of extension class names'));

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
    }

    #[Test]
    public function it_registers_routes_beside_a_disabled_config_that_is_not_a_list_without_route_extensions(): void
    {
        config(['rest.extensions' => [CountingExtension::class], 'rest.disabled_extensions' => ['counting' => CountingExtension::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class], $this->search()->middleware());
        $this->assertSame([], ExtensionLog::$built);
        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.disabled_extensions', 'a list of extension class names'));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }

    #[Test]
    public function it_registers_routes_beside_an_extension_config_of_another_kind_that_is_not_a_list(): void
    {
        config(['rest.extensions' => [1 => CountingExtension::class, 2 => 'Missing\Extension']]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class], $this->search()->middleware());
        $this->assertSame([], ExtensionLog::$built);
        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.extensions', 'a list of extension class names'));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }

    private function search(): Route
    {
        return $this->app['router']->getRoutes()->getByName('routed-tickets.search') ?? $this->fail('The search route is not registered.');
    }
}
