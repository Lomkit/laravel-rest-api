<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class MutateCreateOperationsTest extends TestCase
{
    public function test_creating_a_resource_using_not_authorized_field(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => ['not_authorized_field' => true]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.attributes']]);
    }

    public function test_creating_a_resource(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [$modelToCreate],
        );
    }

    public function test_creating_a_resource_with_creating_belongs_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

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
                            'belongsToRelation' => [
                                'operation' => 'create',
                                'attributes' => []
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [$modelToCreate],
        );
    }

    public function test_creating_a_resource_with_attaching_belongs_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

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
                            'belongsToRelation' => [
                                'operation' => 'attach',
                                'key' => $belongsToRelationToAttach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [$modelToCreate],
        );
    }

    public function test_creating_a_resource_with_updating_belongs_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

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
                            'belongsToRelation' => [
                                'operation' => 'update',
                                'key' => $belongsToRelationToAttach->getKey(),
                                'attributes' => ['number' => 5001] // 5001 because with factory it can't exceed 5000
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $belongsToRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongs_to_relation_id,
            $belongsToRelationToAttach->getKey()
        );
    }
    // ============== TODOOOOOOOOOOOOOOOOOOOOOO

//    public function test_creating_a_resource_with_creating_has_many_relation(): void
//    {
//        $modelToCreate = ModelFactory::new()->makeOne();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//        Gate::policy(HasManyRelation::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/mutate',
//            [
//                'mutate' => [
//                    [
//                        'operation' => 'create',
//                        'attributes' => [
//                            'name' => $modelToCreate->name,
//                            'number' => $modelToCreate->number
//                        ],
//                        'relations' => [
//                            'hasManyRelation' => [
//                                [
//                                    'operation' => 'create',
//                                    'attributes' => []
//                                ]
//                            ]
//                        ]
//                    ],
//                ],
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        //@TODO: correct bug on distant has many relation :(
//        dd($response->getContent());
//
//        $this->assertMutatedResponse(
//            $response,
//            [$modelToCreate],
//        );
//    }

//    public function test_creating_a_resource_with_attaching_has_many_relation(): void
//    {
//        $modelToCreate = ModelFactory::new()->makeOne();
//        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//        Gate::policy(BelongsToRelation::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/mutate',
//            [
//                'mutate' => [
//                    [
//                        'operation' => 'create',
//                        'attributes' => [
//                            'name' => $modelToCreate->name,
//                            'number' => $modelToCreate->number
//                        ],
//                        'relations' => [
//                            'hasManyRelation' => [
//                                'operation' => 'attach',
//                                'key' => $belongsToRelationToAttach->getKey()
//                            ]
//                        ]
//                    ],
//                ],
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $this->assertMutatedResponse(
//            $response,
//            [$modelToCreate],
//        );
//    }
//
//    public function test_creating_a_resource_with_updating_has_many_relation(): void
//    {
//        $modelToCreate = ModelFactory::new()->makeOne();
//        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//        Gate::policy(BelongsToRelation::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/mutate',
//            [
//                'mutate' => [
//                    [
//                        'operation' => 'create',
//                        'attributes' => [
//                            'name' => $modelToCreate->name,
//                            'number' => $modelToCreate->number
//                        ],
//                        'relations' => [
//                            'hasManyRelation' => [
//                                'operation' => 'update',
//                                'key' => $belongsToRelationToAttach->getKey(),
//                                'attributes' => ['number' => 5001] // 5001 because with factory it can't exceed 5000
//                            ]
//                        ]
//                    ],
//                ],
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $this->assertMutatedResponse(
//            $response,
//            [$modelToCreate],
//        );
//
//        // Here we test that the number has been modified on the relation
//        $this->assertEquals(
//            $belongsToRelationToAttach->fresh()->number,
//            5001
//        );
//
//        // Here we test that the model is correctly linked
//        $this->assertEquals(
//            Model::find($response->json('created.0'))->belongs_to_relation_id,
//            $belongsToRelationToAttach->getKey()
//        );
//    }
}