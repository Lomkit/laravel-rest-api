<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\BookingController;
use Lomkit\Rest\Tests\Fixtures\Atlas\ContinentController;
use Lomkit\Rest\Tests\Fixtures\Atlas\JournalController;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class CheckPerimeterTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string JOURNAL_PROBLEM = 'Lomkit\Rest\Tests\Fixtures\Atlas\JournalResource does not override perimeterQuery(): every row is visible to users who pass viewAny. Override perimeterQuery() to limit the rows, or mark the resource with #[WithoutPerimeter] when every row is meant for these users.';

    private const string EXPORT_PROBLEM = "Lomkit\Rest\Tests\Fixtures\Atlas\ExportBookingsAction of Lomkit\Rest\Tests\Fixtures\Atlas\BookingResource runs on rows without authorizeEach(): no policy ability is checked on each row. Return the ability to check from authorizeEach(), such as 'update', or 'view' for an action that only reads.";

    #[Test]
    public function it_reports_the_perimeters_the_marked_resources_and_the_checked_actions(): void
    {
        $this->expose('trips', TripController::class);
        $this->expose('continents', ContinentController::class);

        $output = $this->check(0, TripController::class, ContinentController::class);

        $this->assertSame(
            "  routes and policies ... 14 routes authenticated, 2 policies found\n"
            ."  rows ... 1 resource with a perimeter, 2 marked #[WithoutPerimeter]\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\ContinentResource ... #[WithoutPerimeter]\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... #[WithoutPerimeter]\n"
            ."  actions ... 1 action checks each row\n",
            $output,
        );
    }

    #[Test]
    public function it_fails_for_a_resource_without_a_perimeter(): void
    {
        $this->expose('journals', JournalController::class);

        $output = $this->check(1, JournalController::class);

        $this->assertSame(
            "  routes and policies ... 6 routes authenticated, 1 policy found\n"
            ."  rows ... FAILED\n"
            .'  ⇂ '.rtrim(self::JOURNAL_PROBLEM, '.')."\n"
            ."  actions ... no action runs on rows\n",
            $output,
        );
    }

    #[Test]
    public function it_fails_for_an_action_on_rows_without_authorize_each(): void
    {
        $this->expose('bookings', BookingController::class);

        $output = $this->check(1, BookingController::class);

        $this->assertSame(
            "  routes and policies ... 7 routes authenticated, 1 policy found\n"
            ."  rows ... 1 resource with a perimeter\n"
            ."  actions ... FAILED\n"
            .'  ⇂ '.rtrim(self::EXPORT_PROBLEM, '.')."\n",
            $output,
        );
    }

    #[Test]
    public function it_reports_the_rows_and_the_actions_as_json(): void
    {
        $this->expose('trips', TripController::class);
        $this->expose('journals', JournalController::class);
        $this->expose('bookings', BookingController::class);
        $this->expose('continents', ContinentController::class);

        $exitCode = Artisan::call('rest:check', ['controllers' => [TripController::class, JournalController::class, BookingController::class, ContinentController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            [
                'routes' => 27,
                'policies' => 4,
                'problems' => [],
                'search' => '/api/trips/search',
                'rows' => [
                    'perimeter' => ['Lomkit\Rest\Tests\Fixtures\Atlas\TripResource', 'Lomkit\Rest\Tests\Fixtures\Atlas\BookingResource'],
                    'without_perimeter' => ['Lomkit\Rest\Tests\Fixtures\Atlas\ContinentResource', 'Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource'],
                    'problems' => [self::JOURNAL_PROBLEM],
                ],
                'actions' => [
                    'checked' => [['resource' => 'Lomkit\Rest\Tests\Fixtures\Atlas\TripResource', 'action' => 'Lomkit\Rest\Tests\Fixtures\Atlas\CancelTripsAction', 'ability' => 'update']],
                    'problems' => [self::EXPORT_PROBLEM],
                ],
            ],
            json_decode($output->fetch(), true),
        );
    }

    #[Test]
    public function it_checks_every_controller_that_a_route_uses_without_arguments(): void
    {
        $this->expose('trips', TripController::class);
        $this->expose('journals', JournalController::class);
        Route::get('api/health', fn (): string => 'ok')->middleware('auth:sanctum');

        $output = $this->check(1);

        $this->assertSame(
            "  routes and policies ... 14 routes authenticated, 2 policies found\n"
            ."  rows ... FAILED\n"
            .'  ⇂ '.rtrim(self::JOURNAL_PROBLEM, '.')."\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... #[WithoutPerimeter]\n"
            ."  actions ... 1 action checks each row\n",
            $output,
        );
    }

    #[Test]
    public function it_passes_without_arguments_when_every_check_passes(): void
    {
        $this->expose('trips', TripController::class);

        $exitCode = Artisan::call('rest:check', ['--json' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            [
                'routes' => 8,
                'policies' => 1,
                'problems' => [],
                'search' => '/api/trips/search',
                'rows' => [
                    'perimeter' => ['Lomkit\Rest\Tests\Fixtures\Atlas\TripResource'],
                    'without_perimeter' => ['Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource'],
                    'problems' => [],
                ],
                'actions' => [
                    'checked' => [['resource' => 'Lomkit\Rest\Tests\Fixtures\Atlas\TripResource', 'action' => 'Lomkit\Rest\Tests\Fixtures\Atlas\CancelTripsAction', 'ability' => 'update']],
                    'problems' => [],
                ],
            ],
            json_decode($output->fetch(), true),
        );
    }

    #[Test]
    public function it_fails_without_arguments_when_no_route_uses_a_controller_of_the_package(): void
    {
        Route::get('api/health', fn (): string => 'ok')->middleware('auth:sanctum');

        $output = $this->check(1);

        $this->assertSame(
            "  routes and policies ... FAILED\n"
            ."  ⇂ No route uses a controller of the package. Register resources with Rest::resource() in routes/api.php, and check that bootstrap/app.php loads routes/api.php\n"
            ."  rows ... 0 resources with a perimeter\n"
            ."  actions ... no action runs on rows\n",
            $output,
        );
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
        $arguments = $controllers === [] ? [] : ['controllers' => $controllers];
        $exitCode = Artisan::call('rest:check', $arguments, $output = new BufferedOutput);
        $contents = $this->summary($output->fetch());

        $this->assertSame($expectedExitCode, $exitCode, $contents);

        return $contents;
    }
}
