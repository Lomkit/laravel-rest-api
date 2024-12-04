<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphedByManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToRelationFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\MorphedByManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToRelation;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphedByManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphOneOfManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphOneResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphToResource;

class SearchIncludingMorphRelationshipsOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_including_morph_to_relation(): void
    {
        $morphTo = MorphToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($morphTo, 'morphToRelation')
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'morphToRelation'],
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
                    'morph_to_relation' => $matchingModel->morphToRelation->only((new MorphToResource())->getFields(app()->make(RestRequest::class))),
                ],
                [
                    'morph_to_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_morph_to_relation_with_concrete_relation(): void
    {
        $morphTo = MorphToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($morphTo, 'morphToForceModelRelation')
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'morphToForceModelRelation'],
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
                    'morph_to_force_model_relation' => $matchingModel->morphToForceModelRelation->only((new MorphToResource())->getFields(app()->make(RestRequest::class))),
                ],
                [
                    'morph_to_force_model_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_morph_one_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        MorphOneRelationFactory::new()
            ->for($matchingModel)
            ->create();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'morphOneRelation'],
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
                    'morph_one_relation' => $matchingModel->morphOneRelation->only(
                        (new MorphOneResource())->getFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'morph_one_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_morph_one_of_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        MorphOneOfManyRelationFactory::new()
            ->for($matchingModel)
            ->create();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'morphOneOfManyRelation'],
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
                    'morph_one_of_many_relation' => $matchingModel->morphOneOfManyRelation->only(
                        (new MorphOneOfManyResource())->getFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'morph_one_of_many_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_morph_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(MorphManyRelationFactory::new()->count(2))
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'morphManyRelation',
                            'sorts'    => [
                                ['field' => 'id', 'direction' => 'asc'],
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
                    'morph_many_relation' => $matchingModel->morphManyRelation()
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            return $relation->only(
                                (new MorphManyResource())->getFields(app()->make(RestRequest::class))
                            );
                        })->toArray(),
                ],
                [
                    'morph_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_morph_to_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(MorphToManyRelationFactory::new()->count(2))
            ->create()->fresh();
        $pivotAccessor = $matchingModel->morphToManyRelation()->getPivotAccessor();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'morphToManyRelation',
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
                    'morph_to_many_relation' => $matchingModel->morphToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new MorphToManyResource())->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource())->relation('morphToManyRelation')->getPivotFields()
                                        );

                                    return $relation;
                                });
                        })
                        ->toArray(),
                ],
                [
                    'morph_to_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_morphed_by_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(MorphedByManyRelationFactory::new()->count(2))
            ->create()->fresh();
        $pivotAccessor = $matchingModel->morphedByManyRelation()->getPivotAccessor();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'morphedByManyRelation',
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
                    'morphed_by_many_relation' => $matchingModel->morphedByManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new MorphedByManyResource())->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource())->relation('morphedByManyRelation')->getPivotFields()
                                        );

                                    return $relation;
                                });
                        })
                        ->toArray(),
                ],
                [
                    'morphed_by_many_relation' => [],
                ],
            ]
        );
    }
}
