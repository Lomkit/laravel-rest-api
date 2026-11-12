<?php

namespace Lomkit\Rest\Tests\Feature\Transactions;

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\Interruptions;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\VersionedDocumentController;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\VersionedDocumentResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class VersionedWritesTest extends PackageTestCase
{
    private const array MUTATE = ['mutate' => [
        ['operation' => 'update', 'key' => 1, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['title' => 'Final report']],
    ]];

    /** @var list<string> */
    private array $transactions = [];

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $default = $app['config']->get('database.default');
        $secondary = [...$app['config']->get("database.connections.{$default}"), 'prefix' => 'secondary_'];

        $app['config']->set('database.connections.secondary', $secondary);
        $app['config']->set('database.connections.competitor', $secondary);
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        Rest::resource('versioned-documents', VersionedDocumentController::class);
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
        $this->travelTo('2026-03-01 10:00:00');
        Interruptions::reset([]);
        VersionedDocumentResource::$whileWriting = null;
        VersionedDocumentResource::$confirms = false;
        VersionedDocumentResource::$asked = 0;
        SecondaryDocument::query()->create(['title' => 'Annual report', 'updated_at' => '2026-01-01 08:00:00']);

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
    public function it_reads_the_version_again_when_it_runs_a_deadlocked_transaction_again(): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);

        $response = $this->postJson('/versioned-documents/mutate', self::MUTATE);

        $response->assertOk();
        $this->assertSame(['mutatedAll', 'mutatedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'], $this->transactions);
        $this->assertSame(['Final report', '2026-03-01 10:00:00'], [$this->document()->title, $this->document()->updated_at?->format('Y-m-d H:i:s')]);
    }

    #[Test]
    #[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
    public function it_asks_for_the_confirmation_once_when_it_runs_a_deadlocked_transaction_again(): void
    {
        config(['rest.transactions.attempts' => 2]);
        VersionedDocumentResource::$confirms = true;
        $token = $this->postJson('/versioned-documents/mutate', self::MUTATE)
            ->assertStatus(428)
            ->json('confirmation.token');
        Interruptions::reset([Interruptions::deadlock()]);

        $response = $this->postJson('/versioned-documents/mutate', [...self::MUTATE, 'confirmation' => $token]);

        $response->assertOk();
        $this->assertSame([2, ['mutatedAll', 'mutatedAll']], [VersionedDocumentResource::$asked, Interruptions::$runs]);
        $this->assertSame('Final report', $this->document()->title);
    }

    #[Test]
    public function it_lets_one_of_two_updates_that_send_the_same_version_write(): void
    {
        if (DB::connection('secondary')->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite locks the whole database for a write.');
        }

        $locked = [];
        VersionedDocumentResource::$whileWriting = function () use (&$locked): void {
            try {
                DB::connection('competitor')
                    ->table('documents')
                    ->where('id', 1)
                    ->lock('for update nowait')
                    ->first();
                $locked[] = false;
            } catch (QueryException) {
                $locked[] = true;
            }
        };

        $first = $this->postJson('/versioned-documents/mutate', self::MUTATE);
        $second = $this->postJson('/versioned-documents/mutate', ['mutate' => [[...self::MUTATE['mutate'][0], 'attributes' => ['title' => 'Draft report']]]]);

        $first->assertOk();
        $second->assertConflict()->assertExactJson([
            'message' => 'The record was changed by another request.',
            'conflicts' => [['reason' => 'version', 'path' => 'mutate.0', 'key' => 1, 'version' => '2026-03-01T10:00:00.000000Z']],
        ]);
        $this->assertSame([true], $locked);
        $this->assertSame('Final report', $this->document()->title);
    }

    private function document(): SecondaryDocument
    {
        return SecondaryDocument::query()->findOrFail(1);
    }
}
