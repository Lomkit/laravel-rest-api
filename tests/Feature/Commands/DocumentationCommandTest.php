<?php

namespace Lomkit\Rest\Tests\Feature\Commands;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\CallRestApiAction;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class DocumentationCommandTest extends TestCase
{
    public function test_create_documentation_class(): void
    {
        $this->artisan('rest:documentation', ['--path' => './.phpunit.cache'])->assertOk();

        $this->assertFileExists('./.phpunit.cache/openapi.json');
    }
}