<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Atlas\KeptTripController;
use Lomkit\Rest\Tests\Fixtures\Atlas\RemoteTripController;
use Lomkit\Rest\Tests\Fixtures\Atlas\TripController;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
class CheckDetachTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string COUNTRY_PROBLEM = 'Lomkit\Rest\Tests\Fixtures\Atlas\TripResource accepts detach on its [country] relation, but trips.country_id does not accept null: each detach is refused with a 422. Add ->withoutOperations(MutationOperation::Detach) to the relation, or make the column nullable.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
        });
    }

    #[Test]
    public function it_fails_for_a_relation_that_detaches_a_foreign_key_that_does_not_accept_null(): void
    {
        $this->expose('trips', TripController::class);

        $output = $this->check(1, TripController::class);

        $this->assertSame(
            "  routes and policies ... 8 routes authenticated, 1 policy found\n"
            ."  rows ... 1 resource with a perimeter, 1 marked #[WithoutPerimeter]\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\CountryResource ... #[WithoutPerimeter]\n"
            ."  actions ... 1 action checks each row\n"
            ."  relations ... FAILED\n"
            .'  ⇂ '.rtrim(self::COUNTRY_PROBLEM, '.')."\n",
            $output,
        );
    }

    #[Test]
    public function it_passes_for_a_relation_that_withdraws_detach(): void
    {
        $this->expose('trips', KeptTripController::class);

        $output = $this->check(0, KeptTripController::class);

        $this->assertStringEndsWith("  relations ... no relation accepts detach\n", $output);
    }

    #[Test]
    public function it_passes_for_a_relation_that_detaches_a_foreign_key_that_accepts_null(): void
    {
        Schema::drop('trips');
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->nullable();
        });
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);

        $this->assertStringEndsWith("  relations ... 1 relation accepts detach\n", $output);
    }

    #[Test]
    public function it_reports_the_relations_as_json(): void
    {
        $this->expose('trips', TripController::class);
        $this->expose('kept-trips', KeptTripController::class);

        $exitCode = Artisan::call('rest:check', ['controllers' => [TripController::class, KeptTripController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame(1, $exitCode);
        $this->assertIsArray($report);
        $this->assertSame(['detachable' => [], 'skipped' => [], 'problems' => [self::COUNTRY_PROBLEM]], $report['relations']);
    }

    #[Test]
    public function it_skips_a_relation_whose_table_does_not_exist(): void
    {
        Schema::drop('trips');
        $this->expose('trips', TripController::class);

        $output = $this->check(0, TripController::class);

        $this->assertStringEndsWith(
            "  relations ... no relation accepts detach, 1 skipped\n"
            ."  Lomkit\Rest\Tests\Fixtures\Atlas\TripResource [country] ... skipped, no trips table\n",
            $output,
        );
    }

    #[Test]
    public function it_fails_with_the_connection_whose_columns_cannot_be_read(): void
    {
        config(['database.connections.remote' => ['driver' => 'sqlite', 'database' => sys_get_temp_dir(), 'prefix' => '']]);
        $this->expose('remote-trips', RemoteTripController::class);

        $output = $this->check(1, RemoteTripController::class);

        $this->assertStringContainsString("  relations ... FAILED\n  ⇂ Lomkit\Rest\Tests\Fixtures\Atlas\RemoteTripResource [country]: the columns of the trips table cannot be read on the [remote] connection: ", $output);
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
