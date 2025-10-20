<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchSelectingOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_selecting_unauthorized_relation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'selects' => [
                        ['field' => 'not_authorized_field'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.selects.0.field']]);
    }

    public function test_getting_a_list_of_resources_selecting_id_field(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'selects' => [
                        ['field' => 'id'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [],
            ['id']
        );
    }

    public function test_getting_a_list_of_resources_selecting_two_fields(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'selects' => [
                        ['field' => 'id'],
                        ['field' => 'number'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [],
            ['id', 'number']
        );
    }

    public function test_getting_a_list_of_resources_deep_selecting_fields(): void
    {
        $matchingModel = ModelFactory::new()->has(HasManyRelationFactory::new()->count(2), 'hasManyRelation')->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'selects' => [
                        ['field' => 'id'],
                    ],
                    'includes' => [
                        [
                            'relation' => 'hasManyRelation',
                            'selects'  => [
                                ['field' => 'id'],
                            ],
                            'includes' => [
                                [
                                    'relation' => 'model',
                                    'selects'  => [
                                        ['field' => 'id'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_many_relation' => $matchingModel->hasManyRelation()
                        ->orderBy('id')
                        ->with('model:id')
                        ->get()
                        ->map(function ($relation) {
                            $relation->model = $relation->model->toArray();

                            return $relation->only(['id', 'model']);
                        })->toArray(),
                ],
                [
                    'has_many_relation' => [],
                ],
            ],
            ['id', 'has_many_relation']
        );
    }
}
