<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class BaseResourceCommandTest extends TestCase
{
    public function test_create_base_resource_class(): void
    {
        $this->artisan('rest:base-resource', ['name' => 'TestBaseResource', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestBaseResource.php');
    }
}
