<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneOfManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchIncludingRelationshipsOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_including_unauthorized_relation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    ['relation' => 'unauthorized'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['includes.0.relation']]);
    }

    public function test_getting_a_list_of_resources_including_belongs_to_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($belongsTo)
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    ['relation' => 'belongsToRelation'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'belongs_to_relation' => $matchingModel->belongsToRelation->only((new BelongsToResource)->exposedFields(app()->make(RestRequest::class))),
                ],
                [
                    'belongs_to_relation' => null,
                ]
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_one_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        HasOneRelationFactory::new()
            ->for($matchingModel)
            ->create();


        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    ['relation' => 'hasOneRelation'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'has_one_relation' => $matchingModel->hasOneRelation->only(
                        (new HasOneResource)->exposedFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'has_one_relation' => null,
                ]
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_one_of_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        HasOneOfManyRelationFactory::new()
            ->for($matchingModel)
            ->create();


        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    ['relation' => 'hasOneOfManyRelation'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'has_one_of_many_relation' => $matchingModel->hasOneOfManyRelation->only(
                        (new HasOneOfManyResource)->exposedFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'has_one_of_many_relation' => null,
                ]
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(HasManyRelationFactory::new()->count(2))
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    [
                        'relation' => 'hasManyRelation',
                        'sorts' => [
                            ['field' => 'id', 'direction' => 'asc']
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'has_many_relation' => $matchingModel->hasManyRelation()
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            return $relation->only(
                                (new HasManyResource)->exposedFields(app()->make(RestRequest::class))
                            );
                        })->toArray(),
                ],
                [
                    'has_many_relation' => [],
                ]
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(BelongsToManyRelationFactory::new()->count(2))
            ->create()->fresh();
        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    [
                        'relation' => 'belongsToManyRelation'
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($matchingModel, $pivotAccessor) {
                        return collect($relation->only(
                            array_merge((new BelongsToManyResource)->exposedFields(app()->make(RestRequest::class)), [$pivotAccessor])
                        ))
                            ->pipe(function ($relation) use ($matchingModel, $pivotAccessor) {
                                $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                    ->only(
                                        (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
                                    );
                                return $relation;
                            });
                    })
                        ->toArray(),
                ],
                [
                    'belongs_to_many_relation' => [],
                ]
            ]
        );
    }
}