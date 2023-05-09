<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchIncludingOperationsTest extends TestCase
{
    /** @test */
    public function getting_a_list_of_resources_including_unauthorized_relation(): void
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

    /** @test */
    public function getting_a_list_of_resources_including_belongs_to_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($belongsTo)
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

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

    /** @test */
    public function getting_a_list_of_resources_including_has_one_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        HasOneRelationFactory::new()
            ->for($matchingModel)
            ->create();


        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

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

    /** @test */
    public function getting_a_list_of_resources_including_has_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(HasManyRelationFactory::new()->count(2))
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    ['relation' => 'hasManyRelation'],
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
                    'has_many_relation' => $matchingModel->hasManyRelation->map(function ($relation) {
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

    /** @test */
    public function getting_a_list_of_resources_including_belongs_to_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(BelongsToManyRelationFactory::new()->count(2))
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'includes' => [
                    ['relation' => 'belongsToManyRelation'],
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
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation->map(function ($relation) {
                        return collect($relation->only(
                            array_merge((new BelongsToManyResource)->exposedFields(app()->make(RestRequest::class)), ['pivot'])
                        ))
                            ->pipe(function ($relation) {
                                $relation['pivot'] = $relation['pivot']->toArray();
                                return $relation;
                            });
                    })->toArray(),
                ],
                [
                    'belongs_to_many_relation' => [],
                ]
            ]
        );
    }

    //@TODO: test if id is not selected if it still works !

    //@TODO: belongs to many with pivot fields not working --> ca select tout ! --> bien select les pivots fields nécessaires
    //@TODO: au final ca regroupe le fait de ne pas vouloir select les pivots fields

    //@TODO: if "exposedFields" are empty, it selects all by default - might be corrected with the changes above
}