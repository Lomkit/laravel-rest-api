<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AppAclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BadlyNamedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\FailingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\RivalAclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ThrowingNameExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UnresolvableExtension;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckExtensionsTest extends ScaffoldingTestCase
{
    use ResetsExtensionStubs;
    use RunsTheQuickStart;

    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    private const string IDLE_ACL = 'The [rest.disabled_extensions] config lists '.AclExtension::class.', but no extension of this class is registered or configured, so it disables nothing. Remove it, or list the exact class that the package registers.';

    #[Test]
    #[WithConfig('rest.extensions', [CountingExtension::class])]
    #[WithConfig('rest.disabled_extensions', [NotingExtension::class, AclExtension::class])]
    #[DefineEnvironment('registerNotingAndBoth')]
    public function it_lists_each_extension_with_its_name_class_origin_and_state(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... 2 extensions built, 1 disabled\n"
            .'  ⇂ '.rtrim(self::IDLE_ACL, '.')."\n"
            ."  Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension ... runtime, disabled\n"
            ."  both ... Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension, runtime\n"
            ."  counting ... Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension, config\n",
            $output,
        );
        $this->assertSame([
            'registered' => [
                self::row(null, NotingExtension::class, 'runtime', true),
                self::row('both', BothKindsExtension::class, 'runtime'),
                self::row('counting', CountingExtension::class, 'config'),
            ],
            'problems' => [],
            'notes' => [self::IDLE_ACL],
        ], $report['extensions']);
        $this->assertSame([BothKindsExtension::class, CountingExtension::class, BothKindsExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.disabled_extensions', [AclExtension::class])]
    public function it_notes_a_disabled_entry_that_disables_nothing(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringEndsWith("  extensions ... none registered\n  ⇂ ".rtrim(self::IDLE_ACL, '.')."\n", $output);
        $this->assertSame(['registered' => [], 'problems' => [], 'notes' => [self::IDLE_ACL]], $report['extensions']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [NotingExtension::class])]
    #[DefineEnvironment('registerNoting')]
    public function it_lists_a_class_of_both_sources_once_with_the_config_origin(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(0, TripController::class);

        $this->assertSame([
            'registered' => [
                self::row('noting', NotingExtension::class, 'config'),
            ],
            'problems' => [],
            'notes' => [],
        ], $report['extensions']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AppAclExtension::class])]
    #[WithConfig('rest.disabled_extensions', [AclExtension::class])]
    #[DefineEnvironment('registerAcl')]
    public function it_lists_a_subclass_that_replaces_a_package_extension(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... 1 extension built, 1 disabled\n"
            ."  Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension ... runtime, disabled\n"
            ."  acl ... Lomkit\Rest\Tests\Fixtures\Extensions\AppAclExtension, config\n",
            $output,
        );
        $this->assertSame([AppAclExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [RivalAclExtension::class, CountingExtension::class])]
    #[DefineEnvironment('registerAcl')]
    public function it_names_both_classes_of_a_shared_name_and_lists_the_others(): void
    {
        $this->expose('trips', TripController::class);
        $problem = InvalidExtensionException::sharedName('acl', [AclExtension::class, RivalAclExtension::class])->getMessage();

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... FAILED\n"
            .'  ⇂ '.rtrim($problem, '.')."\n"
            ."  acl ... Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension, runtime\n"
            ."  acl ... Lomkit\Rest\Tests\Fixtures\Extensions\RivalAclExtension, config\n"
            ."  counting ... Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension, config\n",
            $output,
        );
        $this->assertSame([
            'registered' => [
                self::row('acl', AclExtension::class, 'runtime'),
                self::row('acl', RivalAclExtension::class, 'config'),
                self::row('counting', CountingExtension::class, 'config'),
            ],
            'problems' => [$problem],
            'notes' => [],
        ], $report['extensions']);
        $this->assertSame([CountingExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [
        KindlessExtension::class,
        AbstractNotingExtension::class,
        self::MISSING,
        UnresolvableExtension::class,
        ThrowingNameExtension::class,
        FailingExtension::class,
        CountingExtension::class,
    ])]
    public function it_reports_each_extension_that_cannot_be_built_and_lists_the_others(): void
    {
        $failing = 'The [failing] extension '.FailingExtension::class.' cannot be built: '.FailingExtension::MESSAGE;

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringContainsString('  routes ', $output);
        $this->assertStringContainsString("  extensions ... FAILED\n", $output);
        $this->assertStringEndsWith(
            '  ⇂ '.rtrim($failing, '.')."\n"
            ."  kindless ... Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension, config\n"
            ."  unresolvable ... Lomkit\Rest\Tests\Fixtures\Extensions\UnresolvableExtension, config\n"
            ."  failing ... Lomkit\Rest\Tests\Fixtures\Extensions\FailingExtension, config\n"
            ."  counting ... Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension, config\n",
            $output,
        );
        $this->assertArrayHasKey('routes', $report);
        $this->assertIsArray($report['extensions']);
        $this->assertSame([
            self::row('kindless', KindlessExtension::class, 'config'),
            self::row('unresolvable', UnresolvableExtension::class, 'config'),
            self::row('failing', FailingExtension::class, 'config'),
            self::row('counting', CountingExtension::class, 'config'),
        ], $report['extensions']['registered']);
        $problems = $report['extensions']['problems'];
        $this->assertIsArray($problems);
        $this->assertCount(6, $problems);
        $this->assertSame([
            InvalidExtensionException::notInstantiable(AbstractNotingExtension::class)->getMessage(),
            InvalidExtensionException::notAClass(self::MISSING)->getMessage(),
            InvalidExtensionException::unreadableName(ThrowingNameExtension::class, new RuntimeException(ThrowingNameExtension::MESSAGE))->getMessage(),
            InvalidExtensionException::noKind(KindlessExtension::class, 'kindless')->getMessage(),
        ], array_slice($problems, 0, 4));
        $this->assertIsString($problems[4]);
        $this->assertStringStartsWith('The [unresolvable] extension '.UnresolvableExtension::class.' cannot be built: Unresolvable dependency resolving', $problems[4]);
        $this->assertSame($failing, $problems[5]);
        $this->assertSame([CountingExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [CountingExtension::class, 1, BadlyNamedExtension::class])]
    #[WithConfig('rest.disabled_extensions', [false])]
    #[DefineEnvironment('registerNoting')]
    public function it_lists_the_other_extensions_beside_a_refused_config_entry(): void
    {
        $report = $this->report(1, TripController::class);

        $this->assertSame([
            'registered' => [
                self::row('noting', NotingExtension::class, 'runtime'),
                self::row('counting', CountingExtension::class, 'config'),
            ],
            'problems' => [
                InvalidExtensionException::notAClassName(ExtensionList::CONFIG, 'int')->getMessage(),
                InvalidExtensionException::notAClassName(ExtensionList::DISABLED, 'bool')->getMessage(),
                InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl')->getMessage(),
            ],
            'notes' => [],
        ], $report['extensions']);
        $this->assertSame([NotingExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['acl' => 'Vendor\Acl\AccessControl'])]
    #[WithConfig('rest.disabled_extensions', 'acl')]
    #[DefineEnvironment('registerNoting')]
    public function it_reports_extension_configs_that_are_not_lists(): void
    {
        $this->expose('trips', TripController::class);
        $problems = [
            'The [rest.extensions] option must be a list of extension class names.',
            'The [rest.disabled_extensions] option must be a list of extension class names.',
        ];

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringContainsString("  relations ... no relation accepts detach, 1 skipped\n", $output);
        $this->assertStringEndsWith(
            "  extensions ... FAILED\n"
            .'  ⇂ '.rtrim($problems[0], '.')."\n"
            .'  ⇂ '.rtrim($problems[1], '.')."\n"
            ."  noting ... Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension, runtime\n",
            $output,
        );
        $this->assertArrayHasKey('routes', $report);
        $this->assertArrayHasKey('relations', $report);
        $this->assertSame([
            'registered' => [
                self::row('noting', NotingExtension::class, 'runtime'),
            ],
            'problems' => $problems,
            'notes' => [],
        ], $report['extensions']);
        $this->assertSame([NotingExtension::class, NotingExtension::class], ExtensionLog::$built);
    }

    protected function registerNoting(Application $app): void
    {
        Rest::registerExtension(NotingExtension::class);
    }

    protected function registerAcl(Application $app): void
    {
        Rest::registerExtension(AclExtension::class);
    }

    protected function registerNotingAndBoth(Application $app): void
    {
        Rest::registerExtensions([NotingExtension::class, BothKindsExtension::class]);
    }

    /** @return array{name: string|null, class: string, origin: string, disabled: bool} */
    private static function row(?string $name, string $class, string $origin, bool $disabled = false): array
    {
        return ['name' => $name, 'class' => $class, 'origin' => $origin, 'disabled' => $disabled];
    }

    private function expose(string $name, string $controller): void
    {
        Route::prefix('api')->group(function () use ($name, $controller): void {
            Rest::resource($name, $controller)
                ->middleware('auth:sanctum')
                ->register();
        });
    }

    private function check(int $expectedExitCode, string ...$controllers): string
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => $controllers], $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame($expectedExitCode, $exitCode, $contents);

        return $contents;
    }

    /** @return array<string, mixed> */
    private function report(int $expectedExitCode, string ...$controllers): array
    {
        $exitCode = Artisan::call('rest:check', ['controllers' => $controllers, '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame($expectedExitCode, $exitCode);
        $this->assertIsArray($report);

        return $report;
    }
}
