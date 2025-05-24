<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchPaginateOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_paginating_unauthorized_limit(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'limit' => 5,
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.limit']]);
    }

    public function test_getting_a_list_of_resources_paginating_second_page(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();
        $matchingModel3 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'page'  => 2,
                    'limit' => 1,
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_paginating_with_many_records(): void
    {
        ModelFactory::new()->count(100)->create()->fresh();
        $matchingModel = ModelFactory::new()->create()->fresh();
        ModelFactory::new()->count(100)->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'page'  => 101,
                    'limit' => 1,
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }
}
