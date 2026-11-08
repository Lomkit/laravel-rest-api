<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedRuns;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffArchivePolicy;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Tenancy\QueueTenancy;
use Lomkit\Rest\Tests\Fixtures\Tenancy\Tenancy;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
#[WithConfig('app.debug', false)]
class TenantQueuedActionsTest extends PackageTestCase
{
    private const array DUE = ['fields' => [['name' => 'due_at', 'value' => '2026-12-01 10:00:00']]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.'.Tenancy::CENTRAL, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        QueuedRuns::$runs = [];
        StaffDocumentResource::$exports = true;
        Rest::resource('staff-documents', StaffDocumentController::class)
            ->withSoftDeletes()
            ->register();
        Gate::policy(Document::class, StaffArchivePolicy::class);
        $this->actingAs(ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@staff.example', 'password' => 'secret']));

        foreach ([1 => 'Budget', 2 => 'Contract'] as $id => $title) {
            Document::create(['id' => $id, 'title' => $title]);
        }

        Tenancy::$connections = ['a' => config('database.default')];
        Tenancy::initialize('a');
    }

    #[\Override]
    protected function tearDown(): void
    {
        Tenancy::forget();

        parent::tearDown();
    }

    #[Test]
    public function it_runs_a_queued_action_in_the_tenant_that_the_worker_initialises_from_the_job(): void
    {
        QueueTenancy::listen($this->app['events'], initializesTenant: true);

        $this->postJson('/staff-documents/actions/export-documents', self::DUE)->assertOk();

        $this->assertSame([[2, [1, 2]]], array_map(fn (array $run): array => [$run['user'], $run['keys']], QueuedRuns::$runs));
        $this->assertSame(['a', Tenancy::$connections['a']], [Tenancy::$tenant, config('database.default')]);
    }

    #[Test]
    public function it_fails_a_queued_action_whose_worker_stays_on_the_central_database(): void
    {
        QueueTenancy::listen($this->app['events'], initializesTenant: false);

        $response = $this->postJson('/staff-documents/actions/export-documents', self::DUE);

        $exception = $response->exception;
        $this->assertInstanceOf(QueryException::class, $exception);
        $this->assertSame(
            [500, Tenancy::CENTRAL, 'select * from "users" where "id" = ? limit 1', [2], []],
            [$response->status(), $exception->getConnectionName(), $exception->getSql(), $exception->getBindings(), QueuedRuns::$runs],
        );
    }
}
