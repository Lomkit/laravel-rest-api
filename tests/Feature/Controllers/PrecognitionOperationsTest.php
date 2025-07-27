<?php

namespace Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class PrecognitionOperationsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('rest.precognition.enabled', true);
    }

    public function test_precognition_getting_a_list_of_resources_aggregating_by_unauthorized_relation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'unauthorized_relation',
                            'type'     => 'min',
                            'field'    => 'id',
                        ],
                    ],
                ],
            ],
            [
                'Precognition' => 'true',
                'Accept'       => 'application/json'
            ]
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.aggregates.0.relation']]);
    }

    public function test_getting_a_list_of_resources_aggregating_by_specifying_field_when_not_necessary(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'exists',
                            'field'    => 'id',
                        ],
                    ],
                ],
            ],
            [
                'Accept'       => 'application/json',
                'Precognition' => 'fields=aggregates.0.relation,aggregates.0.type',
            ]
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.aggregates.0.field']]);
    }

    public function test_precognition_getting_a_list_of_resources_aggregating_by_min_number(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                            'field'    => 'number',
                        ],
                    ],
                ],
            ],
            [
                'Accept'       => 'application/json',
                'Precognition' => 'true',
            ]
        );

        $response->assertNoContent();
    }

    public function test_precognition_getting_a_list_of_resources_aggregating_by_min_number_without_precognition(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                            'field'    => 'number',
                        ],
                    ],
                ],
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                ['belongs_to_many_relation_min_number' => $matchingModel->belongsToManyRelation()->orderBy('number', 'asc')->first()->number],
                ['belongs_to_many_relation_min_number' => $matchingModel2->belongsToManyRelation()->orderBy('number', 'asc')->first()->number],
            ]
        );
    }
}
