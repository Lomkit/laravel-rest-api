<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ResourceCommandTest extends TestCase
{
    public function test_create_resource_class(): void
    {
        $this->artisan('rest:resource', ['name' => 'TestResource', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestResource.php');
    }
}
