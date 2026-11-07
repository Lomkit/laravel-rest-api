<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ArchivedShelf;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ArchivedShelfController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\FailingArchivedShelfController;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('database.connections.archive', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''])]
class ModelConnectionTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[Test]
    public function it_looks_up_a_mutated_key_on_the_connection_of_its_model(): void
    {
        Schema::connection('archive')->create('shelves', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Rest::resource('archived-shelves', ArchivedShelfController::class)->register();
        $poetry = ArchivedShelf::query()->create(['name' => 'Poetry']);

        $response = $this->postJson('/archived-shelves/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $poetry->id,
            'attributes' => ['name' => 'Modern poetry'],
        ]]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => $poetry->id]]]);
        $this->assertSame(['Modern poetry'], ArchivedShelf::query()->pluck('name')->all());
        $this->assertSame(0, Shelf::query()->count());
    }

    #[Test]
    #[WithConfig('app.debug', false)]
    public function it_rolls_a_mutation_back_on_the_connection_of_its_model(): void
    {
        Schema::connection('archive')->create('shelves', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Rest::resource('failing-archived-shelves', FailingArchivedShelfController::class)->register();
        ArchivedShelf::query()->insert([['id' => 1, 'name' => 'Poetry'], ['id' => 2, 'name' => 'Drama']]);
        $transactions = [];
        Event::listen(TransactionBeginning::class, function (TransactionBeginning $event) use (&$transactions): void {
            $transactions[] = 'begin '.$event->connectionName.' '.$event->connection->transactionLevel();
        });
        Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event) use (&$transactions): void {
            $transactions[] = 'rollback '.$event->connectionName.' '.$event->connection->transactionLevel();
        });

        $response = $this->postJson('/failing-archived-shelves/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['name' => 'Poetry II']],
            ['operation' => 'update', 'key' => 2, 'attributes' => ['name' => 'Drama II']],
        ]]);

        $response->assertServerError();
        $this->assertSame(['begin archive 1', 'rollback archive 0'], $transactions);
        $this->assertSame(['Poetry', 'Drama'], ArchivedShelf::query()
            ->orderBy('id')
            ->pluck('name')
            ->all());
    }
}
