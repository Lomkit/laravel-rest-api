<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class ExposedFieldsTest extends TestCase
{

    /** @test */
    public function getting_a_list_of_resources_with_no_resource_exposed_fields(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-exposed-fields/search',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(500);
        $response->assertJson(['message' => 'No exposed fields are specified on resource']);
    }
}