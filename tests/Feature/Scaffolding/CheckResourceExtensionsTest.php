<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\ColumnCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\PassingCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ThrowingCheckExtension;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckResourceExtensionsTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string COLUMN_PROBLEM = 'The [columns] extension '.ColumnCheckExtension::class.' reports '.TripResource::class.': the trips table has no tenant_id column.';

    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    #[WithConfig('rest.extensions', ['columns' => ColumnCheckExtension::class])]
    public function it_reports_the_problem_of_an_extension_check(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(1, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... 1 extension built\n"
            ."  columns ... Lomkit\Rest\Tests\Fixtures\Extensions\ColumnCheckExtension, config\n"
            ."  extension checks ... FAILED\n"
            .'  ⇂ '.rtrim(self::COLUMN_PROBLEM, '.')."\n",
            $output,
        );
    }

    #[Test]
    #[WithConfig('rest.extensions', ['passing' => PassingCheckExtension::class])]
    public function it_counts_the_checks_that_pass(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);

        $this->assertStringEndsWith(
            "  passing ... Lomkit\Rest\Tests\Fixtures\Extensions\PassingCheckExtension, config\n"
            ."  extension checks ... 2 checks passed\n",
            $output,
        );
    }

    #[Test]
    #[WithConfig('rest.extensions', ['columns' => ColumnCheckExtension::class])]
    public function it_runs_each_check_on_each_reached_resource_as_json(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame([
            'checked' => [
                ['extension' => 'columns', 'resource' => TripResource::class],
                ['extension' => 'columns', 'resource' => CountryResource::class],
            ],
            'problems' => [self::COLUMN_PROBLEM],
        ], $report['extension_checks']);
        $this->assertSame([TripResource::class, CountryResource::class], ExtensionLog::$checked);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['throwing' => ThrowingCheckExtension::class])]
    public function it_reports_an_exception_of_a_check_as_a_problem(): void
    {
        $this->expose('trips', TripController::class);
        $class = ThrowingCheckExtension::class;

        $report = $this->report(1, TripController::class);

        $this->assertSame([
            'checked' => [
                ['extension' => 'throwing', 'resource' => TripResource::class],
                ['extension' => 'throwing', 'resource' => CountryResource::class],
            ],
            'problems' => [
                "The [throwing] extension {$class} reports ".TripResource::class.': the schema is read once.',
                "The [throwing] extension {$class} throws while it checks ".TripResource::class.': The schema cannot be read.',
                "The [throwing] extension {$class} reports ".CountryResource::class.': the schema is read once.',
                "The [throwing] extension {$class} throws while it checks ".CountryResource::class.': The schema cannot be read.',
            ],
        ], $report['extension_checks']);
        $this->assertArrayHasKey('routes', $report);
        $this->assertArrayHasKey('rows', $report);
        $this->assertSame([], $report['extensions']['problems'] ?? null);
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function orders(): array
    {
        return [
            'columns first' => [['columns' => ColumnCheckExtension::class, 'throwing' => ThrowingCheckExtension::class]],
            'throwing first' => [['throwing' => ThrowingCheckExtension::class, 'columns' => ColumnCheckExtension::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_runs_the_checks_in_name_order_whatever_the_config_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame([
            ['extension' => 'columns', 'resource' => TripResource::class],
            ['extension' => 'throwing', 'resource' => TripResource::class],
            ['extension' => 'columns', 'resource' => CountryResource::class],
            ['extension' => 'throwing', 'resource' => CountryResource::class],
        ], $report['extension_checks']['checked'] ?? null);
        $this->assertSame(self::COLUMN_PROBLEM, $report['extension_checks']['problems'][0] ?? null);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['counting' => CountingExtension::class])]
    public function it_prints_no_extension_check_line_without_a_checking_extension(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringNotContainsString('extension checks', $output);
        $this->assertSame(['checked' => [], 'problems' => []], $report['extension_checks']);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['columns' => ColumnCheckExtension::class, 'broken' => 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension'])]
    public function it_leaves_a_broken_extension_list_to_the_extensions_section(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame(['checked' => [], 'problems' => []], $report['extension_checks']);
        $this->assertCount(1, $report['extensions']['problems'] ?? []);
        $this->assertSame([], ExtensionLog::$checked);
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
