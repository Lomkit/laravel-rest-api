<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Closure;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RenameSecondaryDocumentsPerRow;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RowRenamedSecondaryDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RowRenamedSecondaryDocumentResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RowTransactionWithHandle;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RowTransactionWithoutHandleRow;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class RowTransactionsTest extends PackageTestCase
{
    private const array COMMITTED = ['begin secondary', 'commit secondary'];

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
        RenameSecondaryDocumentsPerRow::$runs = [];
        RowRenamedSecondaryDocumentResource::$misdeclared = null;
        Rest::resource('row-renamed-documents', RowRenamedSecondaryDocumentController::class)->register();

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
    #[DataProvider('actions')]
    public function it_runs_each_row_in_its_own_transaction_whatever_the_chunk_size(string $action): void
    {
        $this->seedDocuments(['Annual report', 'Roadmap', 'Minutes', 'Agenda', 'Budget plan']);

        $response = $this->postJson("/row-renamed-documents/actions/{$action}");

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 5, 'result' => null]]);
        $this->assertSame([...self::COMMITTED, ...self::COMMITTED, ...self::COMMITTED, ...self::COMMITTED, ...self::COMMITTED], $this->transactions);
        $this->assertSame([
            'before 1 at level 0', 'row 1 at level 1',
            'before 2 at level 0', 'row 2 at level 1',
            'before 3 at level 0', 'row 3 at level 1',
            'before 4 at level 0', 'row 4 at level 1',
            'before 5 at level 0', 'row 5 at level 1',
        ], RenameSecondaryDocumentsPerRow::$runs);
        $this->assertSame(['Renamed', 'Renamed', 'Renamed', 'Renamed', 'Renamed'], $this->titles());
    }

    #[Test]
    #[DataProvider('actions')]
    public function it_rolls_back_the_failed_row_only_and_stops_there(string $action): void
    {
        $this->seedDocuments(['Annual report', 'Roadmap', 'Budget', 'Minutes']);

        $response = $this->postJson("/row-renamed-documents/actions/{$action}");

        $response->assertInternalServerError();
        $this->assertSame([...self::COMMITTED, ...self::COMMITTED, 'begin secondary', 'rollback secondary'], $this->transactions);
        $this->assertSame([
            'before 1 at level 0', 'row 1 at level 1',
            'before 2 at level 0', 'row 2 at level 1',
            'before 3 at level 0', 'row 3 at level 1',
        ], RenameSecondaryDocumentsPerRow::$runs);
        $this->assertSame(['Renamed', 'Renamed', 'Budget', 'Minutes'], $this->titles());
    }

    /** @return array<string, array{string}> */
    public static function actions(): array
    {
        return [
            'in the request' => ['rename-secondary-documents-per-row'],
            'in the jobs of a queued action' => ['queued-rename-secondary-documents-per-row'],
        ];
    }

    /** @param Closure(): Action $action */
    #[Test]
    #[DataProvider('misdeclarations')]
    public function it_refuses_an_action_whose_row_hooks_do_not_match_its_transactions(Closure $action, string $message): void
    {
        RowRenamedSecondaryDocumentResource::$misdeclared = $action();
        $this->withoutExceptionHandling();

        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage($message);

        $this->getJson('/row-renamed-documents?with=actions');
    }

    /** @return array<string, array{Closure(): Action, string}> */
    public static function misdeclarations(): array
    {
        $perRow = RenameSecondaryDocumentsPerRow::class;

        return [
            'Row without handleRow()' => [fn (): Action => RowTransactionWithoutHandleRow::make(), RowTransactionWithoutHandleRow::class.' runs each row in its own transaction (ActionTransaction::Row), but it does not declare handleRow(): with Row, each row reaches handleRow(ActionFields $fields, Model $row) instead of handle().'],
            'Row with handle()' => [fn (): Action => RowTransactionWithHandle::make()->transactionPerRow(), RowTransactionWithHandle::class.' runs each row in its own transaction (ActionTransaction::Row), but it declares handle(): with Row, each row reaches handleRow() and handle() never runs.'],
            'Row without rows' => [fn (): Action => RenameSecondaryDocumentsPerRow::make()->transactionPerRow()->target(ActionTarget::None), $perRow.' runs each row in its own transaction (ActionTransaction::Row), but its target is None: it runs without rows.'],
            'handleRow() with Chunk' => [fn (): Action => RenameSecondaryDocumentsPerRow::make(), $perRow.' declares handleRow(), but it runs with ActionTransaction::Chunk: only ActionTransaction::Row (->transactionPerRow()) calls it.'],
            'handleRow() without transaction' => [fn (): Action => RenameSecondaryDocumentsPerRow::make()->withoutTransaction(), $perRow.' declares handleRow(), but it runs with ActionTransaction::None: only ActionTransaction::Row (->transactionPerRow()) calls it.'],
        ];
    }

    /** @param list<string> $titles */
    private function seedDocuments(array $titles): void
    {
        foreach ($titles as $title) {
            SecondaryDocument::query()->create(['title' => $title]);
        }

        $this->transactions = [];
    }

    /** @return list<string> */
    private function titles(): array
    {
        return SecondaryDocument::query()
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }
}
