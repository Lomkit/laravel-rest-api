<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchInstructionsOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_using_unauthorized_instruction(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'instructions' => [
                        ['name' => 'not_authorized_instruction'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.instructions.0.name']]);
    }

    public function test_getting_a_list_of_resources_instructing_numbered(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'instructions' => [
                        ['name' => 'numbered'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource(),
        );
    }

    public function test_getting_a_list_of_resources_instructing_numbered_with_unauthorized_fields(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'instructions' => [
                        [
                            'name'   => 'numbered',
                            'fields' => [
                                ['name' => 'unauthorized_field', 'value' => 1],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.instructions.0.fields.0.name']]);
    }

    public function test_getting_a_list_of_resources_instructing_numbered_with_unauthorized_validation(): void
    {
        ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'instructions' => [
                        [
                            'name'   => 'numbered',
                            'fields' => [
                                ['name' => 'number', 'value' => 'unauthorized_string'],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.instructions.0.fields.0.value']]);
    }

    public function test_getting_a_list_of_resources_instructing_numbered_with_fields(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 11])->fresh();
        ModelFactory::new()->create(['number' => 5])->fresh();
        ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'instructions' => [
                        [
                            'name'   => 'numbered',
                            'fields' => [
                                ['name' => 'number', 'value' => 10],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource(),
        );
    }
}
