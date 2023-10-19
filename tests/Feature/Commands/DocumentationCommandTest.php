<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class DocumentationCommandTest extends TestCase
{
    public function test_create_documentation_class(): void
    {
        $this->artisan('rest:documentation', ['--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/openapi.json');
    }

    public function test_create_documentation_service_provider_class(): void
    {
        $this->artisan('rest:documentation-provider', ['--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/RestDocumentationServiceProvider.php');
    }
}
