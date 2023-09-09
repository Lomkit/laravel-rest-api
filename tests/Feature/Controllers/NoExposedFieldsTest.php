<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoExposedFieldsResource;

class NoExposedFieldsTest extends TestCase
{
    public function test_search_no_exposed_field_resource(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-exposed-fields/search',
            [

            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new NoExposedFieldsResource()
        );
    }
}
