<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ArchivedShelf;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ArchivedShelfController;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('database.connections.archive', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''])]
class ModelConnectionTest extends PackageTestCase
{
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

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$poetry->id]]);
        $this->assertSame(['Modern poetry'], ArchivedShelf::query()->pluck('name')->all());
        $this->assertSame(0, Shelf::query()->count());
    }
}
