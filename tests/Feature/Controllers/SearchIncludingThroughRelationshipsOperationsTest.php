<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyThroughRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneThroughRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\HasManyThroughRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneThroughRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyThroughResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneThroughResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchIncludingThroughRelationshipsOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_including_has_one_through_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->createOne()->fresh();
        $hasOne = HasOneRelationFactory::new()
            ->for($matchingModel)
            ->createOne();
        $hasOneThrough = HasOneThroughRelationFactory::new()
            ->for($hasOne)
            ->createOne();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasOneThroughRelation'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_one_through_relation' => $matchingModel->hasOneThroughRelation->only((new HasOneThroughResource())->getFields(app()->make(RestRequest::class))),
                ],
                [
                    'has_one_through_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_many_through_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->createOne()->fresh();
        $hasMany = HasManyRelationFactory::new()
            ->for($matchingModel)
            ->createOne();
        $hasManyThrough = HasManyThroughRelationFactory::new()
            ->for($hasMany)
            ->createOne();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasManyThroughRelation'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_many_through_relation' => $matchingModel->hasManyThroughRelation->map(function ($relation) {
                        return $relation->only(
                            (new HasManyThroughResource())->getFields(app()->make(RestRequest::class))
                        );
                    })->toArray(),
                ],
                [
                    'has_many_through_relation' => [],
                ],
            ]
        );
    }
}
