<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\ContinentController;
use Lomkit\Rest\Tests\Fixtures\Atlas\ContinentResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\JournalController;
use Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripResource;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclPerimeterExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AtlasRowsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AtlasRowsExtensionNamedZones;
use Lomkit\Rest\Tests\Fixtures\Extensions\ColumnCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ColumnCheckExtensionNamedZones;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\OtherAtlasRowsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\PageCountMetaExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\PageCountMetaExtensionNamedAbilities;
use Lomkit\Rest\Tests\Fixtures\Extensions\PageCountMetaExtensionNamedInstructions;
use Lomkit\Rest\Tests\Fixtures\Extensions\PassingCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\RelationCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ThrowingCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\TripCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UnappliedAclPerimeterExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UndecidedCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UndecidedMetaCheckExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\UndecidedRowsExtension;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckResourceExtensionsTest extends ScaffoldingTestCase
{
    use ResetsExtensionStubs;
    use RunsTheQuickStart;

    private const string COLUMN_PROBLEM = 'The [columns] extension '.ColumnCheckExtension::class.' reports '.TripResource::class.': the trips table has no tenant_id column.';

    private const string RESTRICTED_JOURNAL_PROBLEM = 'Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource does not override perimeterQuery(): only the extensions [atlas] restrict its rows. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when the rows that these extensions leave are meant for every user who passes viewAny.';

    private const string JOURNAL_PROBLEM = 'Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource does not override perimeterQuery(): every row is visible to users who pass viewAny. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when every row is meant for these users.';

    #[Test]
    #[WithConfig('rest.extensions', [ColumnCheckExtension::class])]
    public function it_runs_each_check_on_each_reached_resource_and_reports_its_problem(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringEndsWith(
            "  extensions ... 1 extension built\n"
            ."  columns ... Lomkit\Rest\Tests\Fixtures\Extensions\ColumnCheckExtension, config\n"
            ."  extension checks ... FAILED\n"
            .'  ⇂ '.rtrim(self::COLUMN_PROBLEM, '.')."\n",
            $output,
        );
        $this->assertSame([
            'checked' => [
                ['extension' => 'columns', 'resource' => TripResource::class],
                ['extension' => 'columns', 'resource' => CountryResource::class],
            ],
            'problems' => [self::COLUMN_PROBLEM],
        ], $report['extension_checks']);
        $this->assertSame([TripResource::class, CountryResource::class, TripResource::class, CountryResource::class], ExtensionLog::$checked);
    }

    #[Test]
    #[WithConfig('rest.extensions', [ColumnCheckExtension::class, UndecidedCheckExtension::class])]
    public function it_names_the_registered_class_of_checking_extensions_bound_to_subclasses(): void
    {
        $columns = new class extends ColumnCheckExtension {};
        $undecided = new class extends UndecidedCheckExtension {};
        $this->app->bind(ColumnCheckExtension::class, static fn (): ColumnCheckExtension => $columns);
        $this->app->bind(UndecidedCheckExtension::class, static fn (): UndecidedCheckExtension => $undecided);
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame([
            self::COLUMN_PROBLEM,
            'The [undecided] extension '.UndecidedCheckExtension::class.' throws while it tells whether it applies to '.TripResource::class.': The config table is missing.',
        ], $report['extension_checks']['problems'] ?? null);
    }

    #[Test]
    #[WithConfig('rest.extensions', [PassingCheckExtension::class])]
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
    #[WithConfig('rest.extensions', [TripCheckExtension::class])]
    public function it_checks_only_the_resources_that_an_extension_applies_to(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringEndsWith("  extension checks ... 1 check passed\n", $output);
        $this->assertSame(['checked' => [['extension' => 'trips', 'resource' => TripResource::class]], 'problems' => []], $report['extension_checks']);
        $this->assertSame([TripResource::class, TripResource::class], ExtensionLog::$checked);
    }

    #[Test]
    #[WithConfig('rest.extensions', [RelationCheckExtension::class])]
    public function it_gives_the_check_the_resource_built_in_the_context_of_rest_check(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame(['The [relations] extension '.RelationCheckExtension::class.' reports '.TripResource::class.': it declares the relations [country].'], $report['extension_checks']['problems'] ?? null);
        $this->assertCount(2, ExtensionLog::$resources);
        [[$extension, $trips], [, $countries]] = ExtensionLog::$resources;
        $this->assertSame([TripResource::class, CountryResource::class], [$trips::class, $countries::class]);
        $this->assertSame($trips, $trips->context()->resource(TripResource::class));
        $this->assertSame($countries, $trips->context()->resource(CountryResource::class));
        $this->assertSame(['relations' => $extension], $trips->context()->extensions()->all());
    }

    #[Test]
    #[WithConfig('rest.extensions', [PassingCheckExtension::class])]
    public function it_reports_a_resource_that_cannot_be_built_and_checks_the_others(): void
    {
        $this->app->bind(CountryResource::class, static fn (): never => throw new RuntimeException('The country settings are missing.'));
        $this->expose('trips', TripController::class);
        $this->expose('continents', ContinentController::class);
        $this->app->bind(ContinentResource::class, static fn (): never => throw new RuntimeException('The continent settings are missing.'));
        $problems = [
            TripResource::class.' [country]: the resource of the relation cannot be built, so no other check reads it: The country settings are missing.',
            ContinentResource::class.' cannot be built, so no other check reads it: The continent settings are missing.',
        ];

        $output = $this->check(1, TripController::class, ContinentController::class);
        $report = $this->report(1, TripController::class, ContinentController::class);

        $this->assertStringContainsString("  declarations ... FAILED\n  ⇂ ".rtrim($problems[0], '.')."\n  ⇂ ".rtrim($problems[1], '.')."\n  rows ... 1 resource with a perimeter\n", $output);
        $this->assertSame(['actions' => 2, 'metrics' => 0, 'instructions' => 0, 'problems' => $problems], $report['declarations']);
        $this->assertSame(['perimeter' => [TripResource::class], 'without_perimeter' => [], 'problems' => [], 'restricted' => [], 'extension_perimeter' => []], $report['rows']);
        $this->assertSame(['checked' => [['extension' => 'passing', 'resource' => TripResource::class]], 'problems' => []], $report['extension_checks']);
        $this->assertSame([TripResource::class, TripResource::class], ExtensionLog::$checked);
    }

    #[Test]
    #[WithConfig('rest.extensions', [ThrowingCheckExtension::class])]
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
    #[WithConfig('rest.extensions', [UndecidedCheckExtension::class])]
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

    /** @return array<string, array{list<class-string>, string, string, string}> */
    public static function orders(): array
    {
        $throwing = 'The [throwing] extension '.ThrowingCheckExtension::class.' reports '.TripResource::class.': the schema is read once.';

        return [
            'zones, an earlier class, last by name' => [[ColumnCheckExtensionNamedZones::class, ThrowingCheckExtension::class], 'throwing', 'zones', $throwing],
            'throwing first in the config' => [[ThrowingCheckExtension::class, ColumnCheckExtensionNamedZones::class], 'throwing', 'zones', $throwing],
        ];
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_runs_the_checks_in_name_order_whatever_the_config_order(array $extensions, string $first, string $second, string $problem): void
    {
        config(['rest.extensions' => $extensions]);
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame([
            ['extension' => $first, 'resource' => TripResource::class],
            ['extension' => $second, 'resource' => TripResource::class],
            ['extension' => $first, 'resource' => CountryResource::class],
            ['extension' => $second, 'resource' => CountryResource::class],
        ], $report['extension_checks']['checked'] ?? null);
        $this->assertSame($problem, $report['extension_checks']['problems'][0] ?? null);
    }

    /** @return array<string, array{class-string}> */
    public static function uncheckedExtensions(): array
    {
        return [
            'an extension of no checked kind' => [CountingExtension::class],
            'a meta extension under a free name' => [PageCountMetaExtension::class],
        ];
    }

    /** @param class-string $extension */
    #[Test]
    #[DataProvider('uncheckedExtensions')]
    public function it_prints_no_extension_check_line_without_a_check_to_run(string $extension): void
    {
        config(['rest.extensions' => [$extension]]);
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringNotContainsString('extension checks', $output);
        $this->assertSame(['checked' => [], 'problems' => []], $report['extension_checks']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [ColumnCheckExtension::class, 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension'])]
    public function it_leaves_a_broken_extension_list_to_the_extensions_section(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame(['checked' => [], 'problems' => []], $report['extension_checks']);
        $this->assertCount(1, $report['extensions']['problems'] ?? []);
        $this->assertSame([], ExtensionLog::$checked);
    }

    /** @return array<string, array{list<class-string>, list<string>}> */
    public static function restrictions(): array
    {
        return [
            'zones, an earlier class, last by name' => [[AtlasRowsExtensionNamedZones::class, OtherAtlasRowsExtension::class], ['other', 'zones']],
            'other first in the config' => [[OtherAtlasRowsExtension::class, AtlasRowsExtensionNamedZones::class], ['other', 'zones']],
        ];
    }

    /**
     * @param  list<class-string>  $extensions
     * @param  list<string>  $names
     */
    #[Test]
    #[DataProvider('restrictions')]
    public function it_names_the_extensions_that_restrict_the_rows_of_a_resource(array $extensions, array $names): void
    {
        config(['rest.extensions' => $extensions]);
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringContainsString(
            "  rows ... 1 resource with a perimeter, 1 marked #[WithoutPerimeter], 1 restricted by extensions\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... #[WithoutPerimeter]\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\TripResource ... restricted by ".implode(', ', $names)."\n"
            .'  actions ',
            $output,
        );
        $this->assertSame([
            'perimeter' => [TripResource::class],
            'without_perimeter' => [CountryResource::class],
            'problems' => [],
            'restricted' => [['resource' => TripResource::class, 'extensions' => $names]],
            'extension_perimeter' => [],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AtlasRowsExtension::class])]
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
            'extension_perimeter' => [],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [OtherAtlasRowsExtension::class])]
    public function it_keeps_the_problem_of_a_resource_that_no_extension_restricts(): void
    {
        $this->expose('journals', JournalController::class);

        $report = $this->report(1, JournalController::class);

        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [self::JOURNAL_PROBLEM],
            'restricted' => [],
            'extension_perimeter' => [],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AclPerimeterExtension::class])]
    public function it_accepts_a_resource_whose_perimeter_an_extension_provides(): void
    {
        $this->expose('journals', JournalController::class);

        $output = $this->check(0, JournalController::class);
        $report = $this->report(0, JournalController::class);

        $this->assertStringContainsString(
            "  rows ... 0 resources with a perimeter, 1 with a perimeter from extensions, 1 restricted by extensions\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource ... perimeter from acl\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource ... restricted by acl\n"
            .'  actions ',
            $output,
        );
        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [],
            'restricted' => [['resource' => JournalResource::class, 'extensions' => ['acl']]],
            'extension_perimeter' => [['resource' => JournalResource::class, 'extensions' => ['acl']]],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AtlasRowsExtension::class, AclPerimeterExtension::class])]
    public function it_names_only_the_extensions_that_provide_the_perimeter(): void
    {
        $this->expose('journals', JournalController::class);

        $output = $this->check(0, JournalController::class);
        $report = $this->report(0, JournalController::class);

        $this->assertStringContainsString(
            "  Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource ... perimeter from acl\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource ... restricted by acl, atlas\n",
            $output,
        );
        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [],
            'restricted' => [['resource' => JournalResource::class, 'extensions' => ['acl', 'atlas']]],
            'extension_perimeter' => [['resource' => JournalResource::class, 'extensions' => ['acl']]],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [UnappliedAclPerimeterExtension::class])]
    public function it_keeps_the_problem_when_the_perimeter_extension_does_not_apply(): void
    {
        $this->expose('journals', JournalController::class);

        $report = $this->report(1, JournalController::class);

        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [self::JOURNAL_PROBLEM],
            'restricted' => [],
            'extension_perimeter' => [],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AtlasRowsExtension::class, AclPerimeterExtension::class])]
    #[WithConfig('rest.disabled_extensions', [AclPerimeterExtension::class])]
    public function it_keeps_the_problem_when_the_perimeter_extension_is_disabled(): void
    {
        $this->expose('journals', JournalController::class);

        $output = $this->check(1, JournalController::class);
        $report = $this->report(1, JournalController::class);

        $this->assertStringNotContainsString('perimeter from', $output);
        $this->assertSame([
            'perimeter' => [],
            'without_perimeter' => [],
            'problems' => [self::RESTRICTED_JOURNAL_PROBLEM],
            'restricted' => [['resource' => JournalResource::class, 'extensions' => ['atlas']]],
            'extension_perimeter' => [],
        ], $report['rows']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AclPerimeterExtension::class])]
    public function it_keeps_the_report_of_a_resource_with_its_own_perimeter_or_without_perimeter(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);
        $report = $this->report(0, TripController::class);

        $this->assertStringContainsString(
            "  rows ... 1 resource with a perimeter, 1 marked #[WithoutPerimeter], 2 restricted by extensions\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... #[WithoutPerimeter]\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\TripResource ... restricted by acl\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... restricted by acl\n"
            .'  actions ',
            $output,
        );
        $this->assertSame([
            'perimeter' => [TripResource::class],
            'without_perimeter' => [CountryResource::class],
            'problems' => [],
            'restricted' => [
                ['resource' => TripResource::class, 'extensions' => ['acl']],
                ['resource' => CountryResource::class, 'extensions' => ['acl']],
            ],
            'extension_perimeter' => [],
        ], $report['rows']);
    }

    /** @return array<string, array{class-string, string|null, list<string>}> */
    public static function packageKeys(): array
    {
        $instructions = PageCountMetaExtensionNamedInstructions::class;
        $abilities = PageCountMetaExtensionNamedAbilities::class;

        return [
            'the instruction meta' => [$instructions, null, [
                InvalidMetaExtensionException::instructionsName('instructions', $instructions, TripResource::class)->getMessage(),
                InvalidMetaExtensionException::instructionsName('instructions', $instructions, CountryResource::class)->getMessage(),
            ]],
            'a configured gates key' => [$abilities, 'abilities', [
                InvalidMetaExtensionException::gatesKeyName('abilities', $abilities, TripResource::class)->getMessage(),
                InvalidMetaExtensionException::gatesKeyName('abilities', $abilities, CountryResource::class)->getMessage(),
            ]],
        ];
    }

    /**
     * @param  class-string  $extension
     * @param  list<string>  $problems
     */
    #[Test]
    #[DataProvider('packageKeys')]
    public function it_reports_a_meta_extension_named_like_a_key_of_the_package_for_each_reached_resource(string $extension, ?string $gatesKey, array $problems): void
    {
        if ($gatesKey !== null) {
            config(['rest.gates.key' => $gatesKey]);
        }

        config(['rest.extensions' => [$extension]]);
        $this->expose('trips', TripController::class);

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringEndsWith(
            "  extension checks ... FAILED\n"
            .'  ⇂ '.rtrim($problems[0], '.')."\n"
            .'  ⇂ '.rtrim($problems[1], '.')."\n",
            $output,
        );
        $this->assertSame(['checked' => [], 'problems' => $problems], $report['extension_checks']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [UndecidedMetaCheckExtension::class])]
    public function it_reports_an_exception_of_applies_to_once_for_an_extension_of_several_checked_kinds(): void
    {
        $this->expose('trips', TripController::class);

        $report = $this->report(1, TripController::class);

        $this->assertSame([
            'checked' => [['extension' => 'plans', 'resource' => CountryResource::class]],
            'problems' => ['The [plans] extension '.UndecidedMetaCheckExtension::class.' throws while it tells whether it applies to '.TripResource::class.': The plan table is missing.'],
        ], $report['extension_checks']);
    }

    #[Test]
    #[WithConfig('rest.extensions', [AtlasRowsExtension::class, UndecidedRowsExtension::class])]
    public function it_names_the_decided_restrictions_beside_an_extension_whose_applies_to_throws(): void
    {
        $this->expose('trips', TripController::class);
        $problem = 'The [undecided] extension '.UndecidedRowsExtension::class.' throws while it tells whether it applies to '.TripResource::class.': The tenant table is missing.';

        $output = $this->check(1, TripController::class);
        $report = $this->report(1, TripController::class);

        $this->assertStringContainsString("  rows ... FAILED\n  ⇂ ".rtrim($problem, '.')."\n", $output);
        $this->assertSame([
            'perimeter' => [TripResource::class],
            'without_perimeter' => [CountryResource::class],
            'problems' => [$problem],
            'restricted' => [
                ['resource' => TripResource::class, 'extensions' => ['atlas']],
                ['resource' => CountryResource::class, 'extensions' => ['undecided']],
            ],
            'extension_perimeter' => [],
        ], $report['rows']);
        $this->assertSame(['checked' => [], 'problems' => []], $report['extension_checks']);
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
