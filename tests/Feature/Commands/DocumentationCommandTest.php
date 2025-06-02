<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class DocumentationCommandTest extends TestCase
{
    public function test_create_documentation_class(): void
    {
        $this->artisan('rest:documentation')->assertOk();

        $this->assertFileExists('./.phpunit.cache/openapi.json');
    }

    public function test_make_documentation_service_provider_command()
    {
        @unlink(app_path('Providers/RestDocumentationServiceProvider.php'));

        $this
            ->artisan('rest:documentation-provider', ['name' => 'RestDocumentationServiceProvider'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Providers/RestDocumentationServiceProvider.php'));
        $this->assertStringContainsString('class RestDocumentationServiceProvider extends ServiceProvider', file_get_contents(app_path('Providers/RestDocumentationServiceProvider.php')));

        unlink(app_path('Providers/RestDocumentationServiceProvider.php'));
    }
}
