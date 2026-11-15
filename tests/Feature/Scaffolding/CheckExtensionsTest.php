<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\FailingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UnresolvableExtension;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckExtensionsTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    public function it_reports_none_registered_in_one_line(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);

        $this->assertStringEndsWith(
            "  relations ... no relation accepts detach, 1 skipped\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\TripResource [country] ... skipped, no trips table\n"
            ."  extensions ... none registered\n",
            $output,
        );
    }

    #[Test]
    #[WithConfig('rest.extensions', ['counting' => CountingExtension::class, 'noting' => false, 'tenant' => false])]
    #[DefineEnvironment('registerNotingAndBoth')]
    public function it_lists_each_extension_with_its_origin(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... 2 extensions built, 2 disabled\n"
            ."  noting ... Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension, runtime, disabled\n"
            ."  both ... Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension, runtime\n"
            ."  counting ... Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension, config\n"
            ."  tenant ... config, disabled\n",
            $output,
        );
        $this->assertSame([BothKindsExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['counting' => CountingExtension::class, 'noting' => false, 'tenant' => false])]
    #[DefineEnvironment('registerNotingAndBoth')]
    public function it_lists_the_extensions_as_json(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(0, TripController::class);

        $this->assertSame([
            'registered' => [
                ['name' => 'noting', 'class' => NotingExtension::class, 'origin' => 'runtime', 'disabled' => true, 'replaces' => null],
                ['name' => 'both', 'class' => BothKindsExtension::class, 'origin' => 'runtime', 'disabled' => false, 'replaces' => null],
                ['name' => 'counting', 'class' => CountingExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
                ['name' => 'tenant', 'class' => null, 'origin' => 'config', 'disabled' => true, 'replaces' => null],
            ],
            'problems' => [],
        ], $report['extensions']);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['noting' => BothKindsExtension::class])]
    #[DefineEnvironment('registerNoting')]
    public function it_names_the_runtime_class_that_a_config_entry_replaces(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringContainsString("  extensions ... 1 extension built\n  noting ", $output);
        $this->assertStringEndsWith(
            " Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension, config, replaces Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension\n",
            $output,
        );
        $this->assertSame([
            'registered' => [
                ['name' => 'noting', 'class' => BothKindsExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => NotingExtension::class],
            ],
            'problems' => [],
        ], $report['extensions']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [
        'kindless' => KindlessExtension::class,
        'abstract' => AbstractNotingExtension::class,
        'missing' => self::MISSING,
        'unresolvable' => UnresolvableExtension::class,
        'counting' => CountingExtension::class,
    ])]
    public function it_reports_each_extension_that_cannot_be_built(): void
    {
        $report = $this->report(1, TripController::class);

        $this->assertIsArray($report['extensions']);
        $this->assertSame([
            ['name' => 'kindless', 'class' => KindlessExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
            ['name' => 'abstract', 'class' => AbstractNotingExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
            ['name' => 'missing', 'class' => self::MISSING, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
            ['name' => 'unresolvable', 'class' => UnresolvableExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
            ['name' => 'counting', 'class' => CountingExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
        ], $report['extensions']['registered']);
        $problems = $report['extensions']['problems'];
        $this->assertIsArray($problems);
        $this->assertCount(4, $problems);
        $this->assertSame([
            InvalidExtensionException::noKind('kindless', KindlessExtension::class)->getMessage(),
            InvalidExtensionException::notInstantiable('abstract', AbstractNotingExtension::class)->getMessage(),
            InvalidExtensionException::notAClass('missing', self::MISSING)->getMessage(),
        ], array_slice($problems, 0, 3));
        $this->assertIsString($problems[3]);
        $this->assertStringStartsWith('The [unresolvable] extension '.UnresolvableExtension::class.' cannot be built: Unresolvable dependency resolving', $problems[3]);
        $this->assertSame([CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', [NotingExtension::class])]
    public function it_reports_a_config_entry_without_a_name(): void
    {
        $problem = InvalidExtensionException::missingName(NotingExtension::class)->getMessage();

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringEndsWith("  extensions ... FAILED\n  ⇂ ".rtrim($problem, '.')."\n", $output);
        $this->assertSame(['registered' => [], 'problems' => [$problem]], $report['extensions']);
    }

    #[Test]
    #[WithConfig('rest.extensions', 'acl')]
    #[DefineEnvironment('registerNoting')]
    public function it_reports_an_extension_config_that_is_not_a_map(): void
    {
        $this->expose('trips', TripController::class);
        $problem = 'The [rest.extensions] option must be a map of extension names to class names or false.';

        $output = $this->check(1, TripController::class);

        $this->assertStringContainsString("  relations ... no relation accepts detach, 1 skipped\n", $output);
        $this->assertStringEndsWith(
            "  extensions ... FAILED\n"
            .'  ⇂ '.rtrim($problem, '.')."\n"
            ."  noting ... Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension, runtime\n",
            $output,
        );
    }

    #[Test]
    #[WithConfig('rest.extensions', 'acl')]
    #[DefineEnvironment('registerNoting')]
    public function it_reports_an_extension_config_that_is_not_a_map_as_json(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertArrayHasKey('routes', $report);
        $this->assertArrayHasKey('relations', $report);
        $this->assertSame([
            'registered' => [
                ['name' => 'noting', 'class' => NotingExtension::class, 'origin' => 'runtime', 'disabled' => false, 'replaces' => null],
            ],
            'problems' => ['The [rest.extensions] option must be a map of extension names to class names or false.'],
        ], $report['extensions']);
        $this->assertSame([NotingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['counting' => CountingExtension::class, 'Bad' => NotingExtension::class, 0 => BothKindsExtension::class])]
    #[DefineEnvironment('registerNoting')]
    public function it_lists_the_other_extensions_beside_a_refused_config_entry(): void
    {
        $report = $this->report(1, TripController::class);

        $this->assertSame([
            'registered' => [
                ['name' => 'noting', 'class' => NotingExtension::class, 'origin' => 'runtime', 'disabled' => false, 'replaces' => null],
                ['name' => 'counting', 'class' => CountingExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
            ],
            'problems' => [
                InvalidExtensionException::invalidName('Bad')->getMessage(),
                InvalidExtensionException::missingName(BothKindsExtension::class)->getMessage(),
            ],
        ], $report['extensions']);
        $this->assertSame([NotingExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['failing' => FailingExtension::class, 'counting' => CountingExtension::class])]
    public function it_reports_an_extension_whose_constructor_throws(): void
    {
        $problem = 'The [failing] extension '.FailingExtension::class.' cannot be built: '.FailingExtension::MESSAGE;

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... FAILED\n"
            .'  ⇂ '.rtrim($problem, '.')."\n"
            ."  failing ... Lomkit\Rest\Tests\Fixtures\Extensions\FailingExtension, config\n"
            ."  counting ... Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension, config\n",
            $output,
        );
        $this->assertStringContainsString('  routes ', $output);
        $this->assertSame([
            'registered' => [
                ['name' => 'failing', 'class' => FailingExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
                ['name' => 'counting', 'class' => CountingExtension::class, 'origin' => 'config', 'disabled' => false, 'replaces' => null],
            ],
            'problems' => [$problem],
        ], $report['extensions']);
        $this->assertArrayHasKey('routes', $report);
        $this->assertSame([CountingExtension::class, CountingExtension::class], ExtensionLog::$built);
    }

    protected function registerNoting(Application $app): void
    {
        Rest::registerExtension('noting', NotingExtension::class);
    }

    protected function registerNotingAndBoth(Application $app): void
    {
        Rest::registerExtensions(['noting' => NotingExtension::class, 'both' => BothKindsExtension::class]);
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
