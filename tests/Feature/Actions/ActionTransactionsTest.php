<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RenamedSecondaryDocumentController;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class ActionTransactionsTest extends PackageTestCase
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
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('secondary')->create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(fn () => Schema::connection('secondary')->dropIfExists('documents'));
        Rest::resource('renamed-documents', RenamedSecondaryDocumentController::class)->register();

        Event::listen([TransactionBeginning::class, TransactionCommitted::class, TransactionRolledBack::class], function (object $event): void {
            $verb = match ($event::class) {
                TransactionBeginning::class => 'begin',
                TransactionCommitted::class => 'commit',
                default => 'rollback',
            };

            $this->transactions[] = "{$verb} {$event->connectionName}";
        });
    }

    /**
     * @param  list<string>  $titles
     * @param  list<string>  $transactions
     * @param  list<string>  $after
     */
    #[Test]
    #[DataProvider('runs')]
    public function it_runs_the_chunks_in_transactions_on_the_connection_of_the_resource(string $action, array $titles, int $status, array $transactions, array $after): void
    {
        SecondaryDocument::query()->insert(array_map(fn (int $id): array => ['id' => $id, 'title' => $titles[$id - 1]], range(1, count($titles))));

        $response = $this->postJson("/renamed-documents/actions/{$action}");

        $response->assertStatus($status);
        $this->assertSame($transactions, $this->transactions);
        $this->assertSame($after, SecondaryDocument::query()
            ->orderBy('id')
            ->pluck('title')
            ->all());
    }

    /** @return array<string, array{string, list<string>, int, list<string>, list<string>}> */
    public static function runs(): array
    {
        $titles = ['Annual report', 'Roadmap', 'Budget'];

        return [
            'one transaction per chunk, the failed chunk rolled back' => ['rename-secondary-documents', $titles, 500, ['begin secondary', 'commit secondary', 'begin secondary', 'rollback secondary'], ['Renamed', 'Renamed', 'Budget']],
            'one transaction per chunk' => ['rename-secondary-documents', ['Annual report', 'Roadmap', 'Minutes'], 200, ['begin secondary', 'commit secondary', 'begin secondary', 'commit secondary'], ['Renamed', 'Renamed', 'Renamed']],
            'atomic, every chunk rolled back' => ['atomic-rename-secondary-documents', $titles, 500, ['begin secondary', 'rollback secondary'], $titles],
            'atomic, one transaction' => ['atomic-rename-secondary-documents', ['Annual report', 'Roadmap', 'Minutes'], 200, ['begin secondary', 'commit secondary'], ['Renamed', 'Renamed', 'Renamed']],
        ];
    }
}
