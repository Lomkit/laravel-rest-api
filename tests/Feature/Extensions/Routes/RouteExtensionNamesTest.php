<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Routes;

use Illuminate\Routing\Route;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\BadlyNamedStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutesNamedB;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RoutedStampNamedLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampNamedLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutesNamedA;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\RoutedTicketController;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\RenamableExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class RouteExtensionNamesTest extends HelpdeskTestCase
{
    private const string STAMP = StampsRequests::class.':a';

    #[Before]
    #[After]
    public function resetTheStubs(): void
    {
        StampRoutes::reset();
        OtherStampRoutes::reset();
        ExtensionLog::reset();
        RoutedStampNamedLimits::$built = 0;
    }

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

    #[Test]
    public function it_skips_a_disabled_route_extension(): void
    {
        config(['rest.extensions' => [StampRoutes::class, OtherStampRoutesNamedB::class], 'rest.disabled_extensions' => [StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':b'], $this->search()->middleware());
        $this->assertSame([0, 1], [StampRoutes::$built, OtherStampRoutes::$built]);
    }

    #[Test]
    public function it_reads_no_name_of_a_disabled_route_extension(): void
    {
        config(['rest.extensions' => [StampRoutes::class, BadlyNamedStampRoutes::class], 'rest.disabled_extensions' => [BadlyNamedStampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->assertSame([StampRoutes::class], StampRoutes::$ran);
    }

    #[Test]
    #[DataProvider('spellings')]
    public function it_disables_a_route_class_written_another_way_but_not_its_subclass(string $disabled): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampRoutesNamedA::class], 'rest.disabled_extensions' => [$disabled]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->assertSame([StampRoutesNamedA::class], StampRoutes::$ran);
    }

    /** @return array<string, array{string}> */
    public static function spellings(): array
    {
        return [
            'as declared' => [StampRoutes::class],
            'with a leading backslash' => ['\\'.StampRoutes::class],
            'in lowercase' => [strtolower(StampRoutes::class)],
        ];
    }

    #[Test]
    public function it_settles_a_shared_name_with_the_disabled_list(): void
    {
        config(['rest.extensions' => [StampRoutes::class, OtherStampRoutes::class], 'rest.disabled_extensions' => [StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, StampsRequests::class.':b'], $this->search()->middleware());
    }

    #[Test]
    public function it_refuses_a_name_that_a_route_class_shares_with_a_class_bound_to_a_route_subclass(): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampNamedLimits::class]]);
        $this->app->bind(StampNamedLimits::class, RoutedStampNamedLimits::class);

        $this->expectExceptionObject(new InvalidExtensionException('The extensions '.StampRoutes::class.' and '.StampNamedLimits::class.' share the name [stamp]. Add one of them to the [rest.disabled_extensions] config, or extend one of them and override name() to give it another name.'));

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
    }

    #[Test]
    public function it_leaves_a_name_shared_with_a_bound_class_of_another_kind_to_the_request(): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampNamedLimits::class]]);
        $this->app->bind(StampNamedLimits::class);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->expectExceptionObject(InvalidExtensionException::sharedName('stamp', [StampRoutes::class, StampNamedLimits::class]));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
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
    public function it_builds_no_disabled_bound_class(): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampNamedLimits::class], 'rest.disabled_extensions' => [StampNamedLimits::class]]);
        $this->app->bind(StampNamedLimits::class, RoutedStampNamedLimits::class);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->assertSame(0, RoutedStampNamedLimits::$built);
    }

    #[Test]
    public function it_refuses_a_map_config_when_routes_register(): void
    {
        config(['rest.extensions' => ['stamp' => StampRoutes::class]]);

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.extensions', 'a list of extension class names'));

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();
    }

    #[Test]
    public function it_disables_nothing_with_a_disabled_config_that_is_not_a_list(): void
    {
        config(['rest.extensions' => [StampRoutes::class], 'rest.disabled_extensions' => ['stamp' => StampRoutes::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
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

    #[Test]
    public function it_leaves_a_name_shared_with_an_unbound_class_of_another_kind_to_the_request(): void
    {
        config(['rest.extensions' => [StampRoutes::class, StampNamedLimits::class]]);

        Rest::resource('routed-tickets', RoutedTicketController::class)->only('search')->register();

        $this->assertSame([EnforceExpectsJson::class, self::STAMP], $this->search()->middleware());
        $this->expectExceptionObject(InvalidExtensionException::sharedName('stamp', [StampRoutes::class, StampNamedLimits::class]));

        $this->withoutExceptionHandling()->postJson('/routed-tickets/search');
    }

    private function search(): Route
    {
        return $this->app['router']->getRoutes()->getByName('routed-tickets.search') ?? $this->fail('The search route is not registered.');
    }
}
