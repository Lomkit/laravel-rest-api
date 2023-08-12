<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
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
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class MutateUpdateOperationsTest extends TestCase
{
    public function test_updating_a_resource_using_not_authorized_field(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'attributes' => ['not_authorized_field' => true]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.attributes']]);
    }

    public function test_updating_a_resource_using_pivot_field_not_following_custom_pivot_rules(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'create',
                                    'attributes' => [],
                                    'pivot' => [
                                        'number' => true
                                    ]
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation.0.pivot.number']]);
    }

    public function test_updating_a_resource(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        $this->assertDatabaseHas(
            $modelToUpdate->getTable(),
            [
                'name' => 'new name',
                'number' => 5001
            ]
        );
    }

    public function test_updating_a_resource_with_creating_belongs_to_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
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
            [],
            [$modelToUpdate],
        );

        $this->assertDatabaseHas(
            $modelToUpdate->getTable(),
            [
                'name' => 'new name',
                'number' => 5001
            ]
        );

        $this->assertNotNull(Model::first()->belongs_to_relation_id);
    }

    public function test_updating_a_resource_with_attaching_belongs_to_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
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
            [],
            [$modelToUpdate],
        );

        $this->assertEquals(
            $belongsToRelationToAttach->getKey(),
            Model::find($response->json('updated.0'))->belongs_to_relation_id
        );
    }

    public function test_updating_a_resource_with_detaching_belongs_to_relation(): void
    {
        $belongsToRelationToDetach = BelongsToRelationFactory::new()
            ->createOne();

        $modelToUpdate = ModelFactory::new()
            ->for($belongsToRelationToDetach)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToRelation' => [
                                'operation' => 'detach',
                                'key' => $belongsToRelationToDetach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        $this->assertNull(
            Model::find($response->json('updated.0'))->belongs_to_relation_id
        );
    }

    public function test_updating_a_resource_with_updating_belongs_to_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToRelationToUpdate = BelongsToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToRelation' => [
                                'operation' => 'update',
                                'key' => $belongsToRelationToUpdate->getKey(),
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
            [],
            [$modelToUpdate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $belongsToRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongs_to_relation_id,
            $belongsToRelationToUpdate->getKey()
        );
    }

    public function test_updating_a_resource_with_creating_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasManyRelation' => [
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


        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_creating_multiple_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasManyRelation' => [
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


        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(), 2
        );
    }

    public function test_updating_a_resource_with_attaching_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToAttach = HasManyRelationFactory::new()
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key' => $hasManyRelationToAttach->getKey()
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_detaching_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToDetach = HasManyRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key' => $hasManyRelationToDetach->getKey()
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(), 0
        );
    }

    public function test_updating_a_resource_with_updating_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToAttach = HasManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key' => $hasManyRelationToAttach->getKey(),
                                    'attributes' => ['number' => 5001] // 5001 because with factory it can't exceed 5000
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );


        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $hasManyRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_creating_has_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneRelation' => [
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
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_attaching_has_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasOneRelationToAttach = HasOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation' => 'attach',
                                'key' => $hasOneRelationToAttach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_detaching_has_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasOneRelationToDetach = HasOneRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation' => 'detach',
                                'key' => $hasOneRelationToDetach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneRelation()->count(), 0
        );
    }

    public function test_updating_a_resource_with_updating_has_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasOneRelationToAttach = HasOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation' => 'update',
                                'key' => $hasOneRelationToAttach->getKey(),
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
            [],
            [$modelToUpdate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $hasOneRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_creating_has_one_of_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
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
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_attaching_has_one_of_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasOneOfManyRelationToAttach = HasOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
                                'operation' => 'attach',
                                'key' => $hasOneOfManyRelationToAttach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_detaching_has_one_of_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasOneOfManyRelationToDetach = HasOneOfManyRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
                                'operation' => 'detach',
                                'key' => $hasOneOfManyRelationToDetach->getKey()
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(), 0
        );
    }

    public function test_updating_a_resource_with_updating_has_one_of_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasOneOfManyRelationToUpdate = HasOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
                                'operation' => 'update',
                                'key' => $hasOneOfManyRelationToUpdate->getKey(),
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
            [],
            [$modelToUpdate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $hasOneOfManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_creating_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
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

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_creating_belongs_to_many_relation_with_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'create',
                                    'attributes' => [],
                                    'pivot' => [
                                        'number' => 20
                                    ]
                                ],
                                [
                                    'operation' => 'create',
                                    'attributes' => [],
                                    'pivot' => [
                                        'number' => 30
                                    ]
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(), 2
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number, 20
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[1]->belongs_to_many_pivot->number, 30
        );
    }

    public function test_updating_a_resource_with_creating_multiple_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
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


        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(), 2
        );
    }

    public function test_updating_a_resource_with_attaching_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyRelationToAttach = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key' => $belongsToManyRelationToAttach->getKey()
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(), 1
        );
    }

    public function test_updating_a_resource_with_detaching_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyRelationToDetach = BelongsToManyRelationFactory::new()
            ->recycle($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key' => $belongsToManyRelationToDetach->getKey()
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(), 0
        );
    }

    public function test_updating_a_resource_with_updating_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyRelationToUpdate = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key' => $belongsToManyRelationToUpdate->getKey(),
                                    'attributes' => ['number' => 5001] // 5001 because with factory it can't exceed 5000
                                ]
                            ]
                        ]
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertMutatedResponse(
            $response,
            [],
            [$modelToUpdate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $belongsToManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(), 1
        );
    }
}