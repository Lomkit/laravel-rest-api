<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\JournalController;
use Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\AtlasRowsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ColumnCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\OtherAtlasRowsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\PassingCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ThrowingCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UndecidedCheckExtension;
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

    private const string RESTRICTED_JOURNAL_PROBLEM = 'Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource does not override perimeterQuery(): only the extensions [atlas] restrict its rows. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when the rows that these extensions leave are meant for every user who passes viewAny.';

    private const string JOURNAL_PROBLEM = 'Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource does not override perimeterQuery(): every row is visible to users who pass viewAny. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when every row is meant for these users.';

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

    #[Test]
    #[WithConfig('rest.extensions', ['undecided' => UndecidedCheckExtension::class])]
    public function it_reports_an_exception_of_applies_to_as_a_problem(): void
    {
        $this->expose('trips', TripController::class);
        $problem = 'The [undecided] extension '.UndecidedCheckExtension::class.' throws while it tells whether it applies to '.TripResource::class.': The config table is missing.';

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringEndsWith(
            "  extension checks ... FAILED\n"
            .'  ⇂ '.rtrim($problem, '.')."\n",
            $output,
        );
        $this->assertSame([
            'checked' => [['extension' => 'undecided', 'resource' => CountryResource::class]],
            'problems' => [$problem],
        ], $report['extension_checks']);
        $this->assertSame([CountryResource::class, CountryResource::class], ExtensionLog::$checked);
        $this->assertArrayHasKey('rows', $report);
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

    /** @return array<string, array{array<string, class-string>}> */
    public static function restrictions(): array
    {
        return [
            'atlas first' => [['atlas' => AtlasRowsExtension::class, 'other' => OtherAtlasRowsExtension::class]],
            'other first' => [['other' => OtherAtlasRowsExtension::class, 'atlas' => AtlasRowsExtension::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('restrictions')]
    public function it_names_the_extensions_that_restrict_the_rows_of_a_resource(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringContainsString(
            "  rows ... 1 resource with a perimeter, 1 marked #[WithoutPerimeter], 1 restricted by extensions\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... #[WithoutPerimeter]\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\TripResource ... restricted by atlas, other\n"
            .'  actions ',
            $output,
        );
        $this->assertSame([
            'perimeter' => [TripResource::class],
            'without_perimeter' => [CountryResource::class],
            'problems' => [],
            'restricted' => [['resource' => TripResource::class, 'extensions' => ['atlas', 'other']]],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['atlas' => AtlasRowsExtension::class])]
    public function it_keeps_the_problem_of_a_restricted_resource_without_a_perimeter(): void
    {
        $this->expose('journals', JournalController::class);

        $output = $this->check(1, JournalController::class);
        $report = $this->report(1, JournalController::class);

        $this->assertStringContainsString(
            "  rows ... FAILED\n"
            .'  ⇂ '.rtrim(self::RESTRICTED_JOURNAL_PROBLEM, '.')."\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource ... restricted by atlas\n",
            $output,
        );
        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [self::RESTRICTED_JOURNAL_PROBLEM],
            'restricted' => [['resource' => JournalResource::class, 'extensions' => ['atlas']]],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', ['other' => OtherAtlasRowsExtension::class])]
    public function it_keeps_the_problem_of_a_resource_that_no_extension_restricts(): void
    {
        $this->expose('journals', JournalController::class);

        $report = $this->report(1, JournalController::class);

        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [self::JOURNAL_PROBLEM],
            'restricted' => [],
        ], $report['rows']);
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
