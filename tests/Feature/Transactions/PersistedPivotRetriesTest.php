<?php

namespace Lomkit\Rest\Tests\Feature\Transactions;

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\Interruptions;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\LinkedDocument;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\LinkedDocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class PersistedPivotRetriesTest extends PackageTestCase
{
    /** @var list<string> */
    private array $transactions = [];

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $default = $app['config']->get('database.default');

        $app['config']->set('database.connections.secondary', [
            ...$app['config']->get("database.connections.{$default}"),
            'prefix' => 'secondary_',
        ]);
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        Rest::resource('linked-documents', LinkedDocumentController::class);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('secondary')->create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });
        Schema::connection('secondary')->create('document_links', function (Blueprint $table): void {
            $table->foreignId('document_id');
            $table->foreignId('linked_id');
            $table->unique(['document_id', 'linked_id']);
        });

        $this->beforeApplicationDestroyed(function (): void {
            Schema::connection('secondary')->dropIfExists('document_links');
            Schema::connection('secondary')->dropIfExists('documents');
        });
        Interruptions::reset([]);
        foreach (['Annual report', 'Roadmap', 'Minutes'] as $title) {
            LinkedDocument::query()->create(['title' => $title]);
        }

        Event::listen([TransactionBeginning::class, TransactionCommitted::class, TransactionRolledBack::class], function (object $event): void {
            $verb = match ($event::class) {
                TransactionBeginning::class => 'begin',
                TransactionCommitted::class => 'commit',
                default => 'rollback',
            };

            $this->transactions[] = "{$verb} {$event->connectionName}";
        });
    }

    #[Test]
    public function it_runs_the_persister_again_when_the_transaction_is_retried(): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);

        $response = $this->postJson('/linked-documents/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 1,
            'relations' => ['links' => [['operation' => 'attach', 'key' => [2]]]],
        ]]]);

        $response->assertOk();
        $this->assertSame(['attach 2', 'attach 2'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'], $this->transactions);
        $this->assertSame([[1, 2]], DB::connection('secondary')
            ->table('document_links')
            ->get()
            ->map(fn (object $row): array => [(int) $row->document_id, (int) $row->linked_id])
            ->all());
    }
}
