<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyThroughRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneThroughRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyThroughRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneThroughRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class MutateCreateThroughOperationsTest extends TestCase
{
    public function test_creating_a_resource_with_creating_has_one_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasOneThroughRelation' => [
                                'operation' => 'create',
                                'attributes' => []
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasOneThroughRelation']]);
    }

    public function test_creating_a_resource_with_attaching_has_one_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        $tempModel = ModelFactory::new()
            ->createOne()->fresh();
        $hasOne = HasOneRelationFactory::new()
            ->for($tempModel)
            ->createOne();
        $hasOneThroughRelationToAttach = HasOneThroughRelationFactory::new()
            ->for($hasOne)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasOneThroughRelation' => [
                                'operation' => 'attach',
                                'key' => $hasOneThroughRelationToAttach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasOneThroughRelation']]);
    }

    public function test_creating_a_resource_with_updating_has_one_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        $tempModel = ModelFactory::new()
            ->createOne()->fresh();
        $hasOne = HasOneRelationFactory::new()
            ->for($tempModel)
            ->createOne();
        $hasOneThroughRelationToUpdate = HasOneThroughRelationFactory::new()
            ->for($hasOne)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasOneThroughRelation' => [
                                'operation' => 'update',
                                'key' => $hasOneThroughRelationToUpdate->getKey(),
                                'attributes' => ['number' => 5001] // 5001 because with factory it can't exceed 5000
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );


        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasOneThroughRelation']]);
    }

    public function test_creating_a_resource_with_creating_has_many_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasManyThroughRelation' => [
                                [
                                    'operation' => 'create',
                                    'attributes' => []
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasManyThroughRelation']]);
    }

    public function test_creating_a_resource_with_creating_multiple_has_many_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasManyThroughRelation' => [
                                [
                                    'operation' => 'create',
                                    'attributes' => []
                                ],
                                [
                                    'operation' => 'create',
                                    'attributes' => []
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );


        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasManyThroughRelation']]);
    }

    public function test_creating_a_resource_with_attaching_has_many_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        $tempModel = ModelFactory::new()
            ->createOne()->fresh();
        $hasMany = HasManyRelationFactory::new()
            ->for($tempModel)
            ->createOne();
        $hasManyThroughRelationToAttach = HasManyThroughRelationFactory::new()
            ->for($hasMany)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasManyThroughRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key' => $hasManyThroughRelationToAttach->getKey()
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasManyThroughRelation']]);
    }

    public function test_creating_a_resource_with_updating_has_many_through_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        $tempModel = ModelFactory::new()
            ->createOne()->fresh();
        $hasMany = HasManyRelationFactory::new()
            ->for($tempModel)
            ->createOne();
        $hasManyThroughRelationToAttach = HasManyThroughRelationFactory::new()
            ->for($hasMany)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ],
                        'relations' => [
                            'hasManyThroughRelation' => [
                                [
                                    'operation' => 'update',
                                    'key' => $hasManyThroughRelationToAttach->getKey(),
                                    'attributes' => ['number' => 5001] // 5001 because with factory it can't exceed 5000
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.hasManyThroughRelation']]);
    }
}