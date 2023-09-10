<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Lomkit\Rest\Tests\Feature\TestCase;

class ResponseCommandTest extends TestCase
{
    public function test_create_response_class(): void
    {
        $this->artisan('rest:response', ['name' => 'TestResponse', '--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/TestResponse.php');
    }
}
