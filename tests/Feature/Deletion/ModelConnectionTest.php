<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ModelConnectionTest extends PackageTestCase
{
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
        $router->prefix('api')->group(function (): void {
            Rest::resource('secondary-documents', SecondaryDocumentController::class)->only('destroy');
        });
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
    }

    #[Test]
    public function it_checks_the_keys_to_destroy_on_the_connection_of_the_model(): void
    {
        $document = SecondaryDocument::query()->create(['title' => 'Annual report']);

        $response = $this->deleteJson('/api/secondary-documents', ['resources' => [$document->id]]);

        $response->assertOk()->assertJsonPath('data.0.title', 'Annual report');
        $this->assertModelMissing($document);
    }

    #[Test]
    public function it_rejects_a_key_missing_from_the_connection_of_the_model(): void
    {
        $response = $this->deleteJson('/api/secondary-documents', ['resources' => [7]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['resources.0' => ['The selected resources.0 is invalid.']]);
    }
}
