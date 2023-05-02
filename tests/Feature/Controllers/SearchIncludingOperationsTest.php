<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
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
                    'belongs_to_relation_id' => $belongsTo->getKey()
                ],
                [
                    'belongs_to_relation' => null,
                    'belongs_to_relation_id' => null
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
                        array_merge((new HasOneResource)->exposedFields(app()->make(RestRequest::class)), ['model_id'])
                    ),
                ],
                [
                    'has_one_relation' => null,
                ]
            ]
        );
    }

    //@TODO: test on every relation that foreign field are needed and if so, automatically include them !
}