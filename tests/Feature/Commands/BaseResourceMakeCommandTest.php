<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class BaseResourceMakeCommandTest extends TestCase
{
    public function test_make_base_resource_command()
    {
        @unlink(app_path('Rest/Resources/Resource.php'));

        $this
            ->artisan('rest:base-resource', ['name' => 'Resource'])
            ->assertOk()
            ->run();

        $this->assertFileExists(app_path('Rest/Resources/Resource.php'));
        $this->assertStringContainsString('class Resource extends RestResource', file_get_contents(app_path('Rest/Resources/Resource.php')));

        unlink(app_path('Rest/Resources/Resource.php'));
    }
}
