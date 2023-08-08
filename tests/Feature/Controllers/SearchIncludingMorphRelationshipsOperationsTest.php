<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphOneOfMany;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToRelationFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\MorphManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToRelation;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphOneOfManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphOneResource;

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
                'includes' => [
                    ['relation' => 'morphToRelation'],
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
                    'morph_to_relation' => $matchingModel->morphToRelation->only((new BelongsToResource)->exposedFields(app()->make(RestRequest::class))),
                ],
                [
                    'morph_to_relation' => null,
                ]
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
                'includes' => [
                    ['relation' => 'morphOneRelation'],
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
                    'morph_one_relation' => $matchingModel->morphOneRelation->only(
                        (new MorphOneResource)->exposedFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'morph_one_relation' => null,
                ]
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
                'includes' => [
                    ['relation' => 'morphOneOfManyRelation'],
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
                    'morph_one_of_many_relation' => $matchingModel->morphOneOfManyRelation->only(
                        (new MorphOneOfManyResource)->exposedFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'morph_one_of_many_relation' => null,
                ]
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
                'includes' => [
                    [
                        'relation' => 'morphManyRelation',
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
                    'morph_many_relation' => $matchingModel->morphManyRelation->map(function ($relation) {
                        return $relation->only(
                            (new MorphManyResource)->exposedFields(app()->make(RestRequest::class))
                        );
                    })->toArray(),
                ],
                [
                    'morph_many_relation' => [],
                ]
            ]
        );
    }

    // @TODO: with morphToMany, verify that you can filter pivot :)
//    public function test_getting_a_list_of_resources_including_belongs_to_many_relation(): void
//    {
//        $matchingModel = ModelFactory::new()
//            ->has(BelongsToManyRelationFactory::new()->count(2))
//            ->create()->fresh();
//        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();
//
//        $matchingModel2 = ModelFactory::new()->create()->fresh();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/search',
//            [
//                'includes' => [
//                    [
//                        'relation' => 'belongsToManyRelation'
//                    ],
//                ],
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $this->assertResourcePaginated(
//            $response,
//            [$matchingModel, $matchingModel2],
//            new ModelResource,
//            [
//                [
//                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
//                        ->orderBy('id', 'desc')
//                        ->get()
//                        ->map(function ($relation) use ($matchingModel, $pivotAccessor) {
//                        return collect($relation->only(
//                            array_merge((new BelongsToManyResource)->exposedFields(app()->make(RestRequest::class)), [$pivotAccessor])
//                        ))
//                            ->pipe(function ($relation) use ($matchingModel, $pivotAccessor) {
//                                $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
//                                    ->only(
//                                        (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
//                                    );
//                                return $relation;
//                            });
//                    })
//                        ->toArray(),
//                ],
//                [
//                    'belongs_to_many_relation' => [],
//                ]
//            ]
//        );
//    }
}