<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Rest as RestFactory;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AppAclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BadlyNamedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ConfiguredExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountsResources;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\InjectedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotAnExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotesResources;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

final class ExtensionRegistrationTest extends PackageTestCase
{
    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    /** @var list<InvalidExtensionException> */
    private array $refusals = [];

    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    public function it_resolves_the_registration_store_when_the_package_registers(): void
    {
        $this->assertTrue($this->app->resolved(RuntimeExtensions::class));
    }

    #[Test]
    #[DefineEnvironment('registerNoting')]
    public function it_registers_an_extension_through_the_facade(): void
    {
        $this->assertSame(['noting'], array_keys($this->context()->extensions()->applicable(ProductResource::class, NotesResources::class)));
    }

    #[Test]
    #[DefineEnvironment('registerSeveral')]
    public function it_registers_several_extensions_through_the_facade(): void
    {
        $this->assertSame(['both', 'counting'], array_keys($this->context()->extensions()->applicable(ProductResource::class, CountsResources::class)));
    }

    #[Test]
    #[WithConfig('rest.extensions', [CountingExtension::class])]
    public function it_reads_the_extensions_of_the_config(): void
    {
        $this->assertSame(['counting'], array_keys($this->context()->extensions()->all()));
    }

    #[Test]
    #[WithConfig('rest.extensions', [NotingExtension::class])]
    #[DefineEnvironment('registerNoting')]
    public function it_counts_a_class_in_the_config_and_at_runtime_once(): void
    {
        $this->assertSame(['noting'], array_keys($this->context()->extensions()->all()));
        $this->assertSame([NotingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.disabled_extensions', [NotingExtension::class])]
    #[DefineEnvironment('registerNoting')]
    public function it_lets_the_config_disable_a_runtime_extension(): void
    {
        $this->assertSame([], $this->context()->extensions()->all());
        $this->assertSame([], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [CountingExtension::class, NotingExtension::class])]
    #[WithConfig('rest.disabled_extensions', [CountingExtension::class])]
    public function it_lets_the_config_disable_a_config_extension(): void
    {
        $this->assertSame(['noting'], array_keys($this->context()->extensions()->all()));
        $this->assertSame([NotingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AppAclExtension::class])]
    #[WithConfig('rest.disabled_extensions', [AclExtension::class])]
    #[DefineEnvironment('registerAcl')]
    public function it_replaces_a_package_extension_with_a_subclass_that_keeps_its_name(): void
    {
        $extensions = $this->context()->extensions()->applicable(ProductResource::class, NotesResources::class);

        $this->assertSame(['acl'], array_keys($extensions));
        $this->assertInstanceOf(AppAclExtension::class, $extensions['acl']);
        $this->assertSame([AppAclExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AppAclExtension::class])]
    #[DefineEnvironment('registerAcl')]
    public function it_refuses_a_replacement_without_the_disabled_entry(): void
    {
        $context = $this->context();

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [AclExtension::class, AppAclExtension::class]));

        $context->extensions();
    }

    #[Test]
    #[WithConfig('rest.disabled_extensions', [AclExtension::class])]
    #[DefineEnvironment('registerAppAcl')]
    public function it_does_not_disable_a_subclass_by_the_entry_of_its_parent(): void
    {
        $this->assertInstanceOf(AppAclExtension::class, $this->context()->extensions()->all()['acl']);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['acl' => 'Vendor\Acl\AccessControl'])]
    public function it_refuses_an_extension_config_that_is_not_a_list(): void
    {
        $context = $this->context();

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.extensions', 'a list of extension class names'));

        $context->extensions();
    }

    #[Test]
    #[WithConfig('rest.disabled_extensions', [NotingExtension::class => true])]
    public function it_refuses_a_disabled_extension_config_that_is_not_a_list(): void
    {
        $context = $this->context();

        $this->expectExceptionObject(InvalidConfigException::mustBe('rest.disabled_extensions', 'a list of extension class names'));

        $context->extensions();
    }

    #[Test]
    #[DefineEnvironment('registerInvalidClasses')]
    public function it_refuses_an_invalid_class_at_registration(): void
    {
        $this->assertEquals([
            InvalidExtensionException::notAClass(self::MISSING),
            InvalidExtensionException::notAnExtension(NotAnExtension::class),
            InvalidExtensionException::notInstantiable(AbstractNotingExtension::class),
            InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl'),
            InvalidExtensionException::instanceGiven('Rest::registerExtensions()', NotingExtension::class),
            InvalidExtensionException::notAClassName('Rest::registerExtensions()', 'int'),
            InvalidExtensionException::notAList('Rest::registerExtensions()'),
        ], $this->refusals);
        $this->assertSame([], $this->app->make(RuntimeExtensions::class)->all());
    }

    #[Test]
    public function it_refuses_an_instance_passed_to_register_extension_as_a_type_error(): void
    {
        $this->expectException(TypeError::class);

        Rest::registerExtension(new NotingExtension);
    }

    #[Test]
    #[WithConfig('rest.extensions', [self::MISSING, KindlessExtension::class])]
    #[DefineEnvironment('registerProductRoutes')]
    public function it_registers_the_routes_beside_an_invalid_extension(): void
    {
        $this->assertSame([], $this->refusals);
        $this->assertNotNull(Route::getRoutes()->getByName('api.products.search'));
    }

    #[Test]
    #[DefineEnvironment('registerNoting')]
    public function it_builds_the_extensions_once_per_context(): void
    {
        $context = $this->context();

        $first = $context->extensions()->all();
        $second = $context->extensions()->all();
        $other = $this->context()->extensions()->all();

        $this->assertSame($context->extensions(), $context->extensions());
        $this->assertSame($first['noting'], $second['noting']);
        $this->assertNotSame($first['noting'], $other['noting']);
        $this->assertSame([NotingExtension::class, NotingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_resolves_nothing_to_read_an_empty_extension_list(): void
    {
        $context = $this->context();
        $context->config();
        $resolved = [];
        $this->app->beforeResolving(function (string $abstract) use (&$resolved): void {
            $resolved[] = $abstract;
        });

        $registry = $context->extensions();

        $this->assertSame([], $registry->all());
        $this->assertSame([], $registry->applicable(ProductResource::class, NotesResources::class));
        $this->assertSame([], $resolved);
        $this->assertSame([], ExtensionLog::$built);
        $this->assertSame([], ExtensionLog::$asked);
    }

    #[Test]
    #[WithConfig('rest.extensions', [ConfiguredExtension::class])]
    #[WithConfig(ConfiguredExtension::RESOURCES, [ProductResource::class])]
    public function it_reads_the_config_of_each_application_in_applies_to(): void
    {
        $other = clone $this->app;
        $other->instance('config', new ConfigRepository([...$this->app->make('config')->all(), 'extension_fixtures' => ['applies_to' => []]]));

        $first = $this->context()->extensions()->applicable(ProductResource::class, NotesResources::class);
        $second = (new RestContext(RestRequest::create('/api/products/search', 'POST'), $other))->extensions()->applicable(ProductResource::class, NotesResources::class);

        $this->assertSame(['configured'], array_keys($first));
        $this->assertSame([], $second);
        $this->assertSame([ConfiguredExtension::class, ConfiguredExtension::class], ExtensionLog::$built);
        $this->assertSame([
            ConfiguredExtension::class.' '.ProductResource::class,
            ConfiguredExtension::class.' '.ProductResource::class,
        ], ExtensionLog::$asked);
    }

    #[Test]
    #[DefineEnvironment('registerInjected')]
    public function it_injects_services_into_an_extension(): void
    {
        $extension = $this->context()->extensions()->all()['injected'];

        $this->assertInstanceOf(InjectedExtension::class, $extension);
        $this->assertSame($this->app->make(Repository::class), $extension->config);
    }

    #[Test]
    public function it_refuses_a_registration_once_the_application_has_booted(): void
    {
        $this->assertTrue($this->app->isBooted());
        $this->assertCount(0, Route::getRoutes());

        $this->expectExceptionObject(InvalidExtensionException::lateRegistration(NotingExtension::class));

        Rest::registerExtension(NotingExtension::class);
    }

    #[Test]
    public function it_refuses_a_registration_in_a_clone_of_the_booted_application(): void
    {
        $clone = clone $this->app;

        $this->assertSame($this->app->make(RuntimeExtensions::class), $clone->make(RuntimeExtensions::class));

        $this->expectExceptionObject(InvalidExtensionException::lateRegistration(NotingExtension::class));

        $clone->make(RestFactory::class)->registerExtension(NotingExtension::class);
    }

    #[Test]
    #[DefineEnvironment('registerAfterAContext')]
    public function it_refuses_a_registration_after_a_context_is_built(): void
    {
        $this->assertRefusedBeforeBoot();
    }

    #[Test]
    #[DefineEnvironment('registerAMapAfterAContext')]
    public function it_refuses_a_map_registration_after_a_context_is_built(): void
    {
        $this->assertRefusedBeforeBoot();
    }

    #[Test]
    #[DefineEnvironment('registerAfterAResourceRoute')]
    public function it_refuses_a_registration_after_a_resource_route_is_registered(): void
    {
        $this->assertRefusedBeforeBoot();
    }

    #[Test]
    #[DefineEnvironment('registerAfterARouteWithoutDeclarations')]
    public function it_refuses_a_registration_after_a_route_without_declarations_is_registered(): void
    {
        $this->assertRefusedBeforeBoot();
    }

    #[Test]
    #[DefineEnvironment('registerAfterAResourceContext')]
    public function it_refuses_a_registration_after_a_resource_reads_its_own_context(): void
    {
        $this->assertRefusedBeforeBoot();
    }

    #[Test]
    #[WithConfig('rest.extensions', [NotingExtension::class, 1])]
    public function it_throws_an_invalid_config_entry_when_the_registry_is_built(): void
    {
        $context = $this->context();

        $this->expectExceptionObject(InvalidExtensionException::notAClassName(ExtensionList::CONFIG, 'int'));

        $context->extensions();
    }

    protected function registerNoting(Application $app): void
    {
        Rest::registerExtension(NotingExtension::class);
    }

    protected function registerAcl(Application $app): void
    {
        Rest::registerExtension(AclExtension::class);
    }

    protected function registerAppAcl(Application $app): void
    {
        Rest::registerExtension(AppAclExtension::class);
    }

    protected function registerSeveral(Application $app): void
    {
        Rest::registerExtensions([NotingExtension::class, CountingExtension::class, BothKindsExtension::class]);
    }

    protected function registerInjected(Application $app): void
    {
        Rest::registerExtension(InjectedExtension::class);
    }

    protected function registerInvalidClasses(Application $app): void
    {
        foreach ([self::MISSING, NotAnExtension::class, AbstractNotingExtension::class, BadlyNamedExtension::class] as $class) {
            $this->tryLate(fn () => Rest::registerExtension($class));
        }

        $this->tryLate(fn () => Rest::registerExtensions([new NotingExtension]));
        $this->tryLate(fn () => Rest::registerExtensions([NotingExtension::class, 1]));
        $this->tryLate(fn () => Rest::registerExtensions([1 => NotingExtension::class]));
    }

    protected function registerProductRoutes(Application $app): void
    {
        $this->tryLate(fn () => Route::prefix('api')->name('api.')->group(fn () => Rest::resource('products', ProductController::class)->register()));
    }

    protected function registerAfterAContext(Application $app): void
    {
        new RestContext(RestRequest::create('/api/products/search', 'POST'), $app);

        $this->tryLate(fn () => Rest::registerExtension(NotingExtension::class));
    }

    protected function registerAMapAfterAContext(Application $app): void
    {
        new RestContext(RestRequest::create('/api/products/search', 'POST'), $app);

        $this->tryLate(fn () => Rest::registerExtensions([NotingExtension::class]));
    }

    protected function registerAfterAResourceRoute(Application $app): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('products', ProductController::class)->register());

        $this->tryLate(fn () => Rest::registerExtension(NotingExtension::class));
    }

    protected function registerAfterARouteWithoutDeclarations(Application $app): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('products', ProductController::class)->only('search', 'details')->register());

        $this->tryLate(fn () => Rest::registerExtension(NotingExtension::class));
    }

    protected function registerAfterAResourceContext(Application $app): void
    {
        (new ProductResource)->context();

        $this->tryLate(fn () => Rest::registerExtension(NotingExtension::class));
    }

    private function tryLate(callable $registration): void
    {
        try {
            $registration();
        } catch (InvalidExtensionException $exception) {
            $this->refusals[] = $exception;
        }
    }

    private function assertRefusedBeforeBoot(): void
    {
        $this->assertEquals([InvalidExtensionException::lateRegistration(NotingExtension::class)], $this->refusals);
        $this->assertSame([], $this->app->make(RuntimeExtensions::class)->all());
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app);
    }
}
