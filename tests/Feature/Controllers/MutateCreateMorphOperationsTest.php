<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphedByManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToRelationFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\MorphedByManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToRelation;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphedByManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\MorphToResource;

class MutateCreateMorphOperationsTest extends TestCase
{
    public function test_creating_a_resource_with_creating_first_morph_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

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
                            'morphToRelation' => [
                                'operation' => 'create',
                                'type' => MorphToResource::class,
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


        $this->assertNotNull(Model::first()->morph_to_relation_id);
        $this->assertNotNull(Model::first()->morph_to_relation_type);
    }

    public function test_creating_a_resource_with_creating_second_morph_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphToRelation' => [
                                'operation' => 'create',
                                'type' => MorphedByManyResource::class,
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


        $this->assertNotNull(Model::first()->morph_to_relation_id);
        $this->assertNotNull(Model::first()->morph_to_relation_type);
    }

    public function test_creating_a_resource_with_attaching_morph_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphToRelationToAttach = MorphToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

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
                            'morphToRelation' => [
                                'operation' => 'attach',
                                'type' => MorphToResource::class,
                                'key' => $morphToRelationToAttach->getKey()
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

        $this->assertEquals(
            $morphToRelationToAttach->getKey(),
            Model::find($response->json('created.0'))->morph_to_relation_id
        );
        $this->assertEquals(
            MorphToRelation::class,
            Model::find($response->json('created.0'))->morph_to_relation_type
        );
    }

    public function test_creating_a_resource_with_updating_morph_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphToRelationToUpdate = MorphToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

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
                            'morphToRelation' => [
                                'operation' => 'update',
                                'key' => $morphToRelationToUpdate->getKey(),
                                'type' => MorphToResource::class,
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
            $morphToRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            $morphToRelationToUpdate->getKey(),
            Model::find($response->json('created.0'))->morph_to_relation_id
        );
        $this->assertEquals(
            MorphToRelation::class,
            Model::find($response->json('created.0'))->morph_to_relation_type
        );
    }


    public function test_creating_a_resource_with_creating_morph_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

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
                            'morphManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_creating_multiple_morph_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

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
                            'morphManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphManyRelation()->count(), 2
        );
    }

    public function test_creating_a_resource_with_attaching_morph_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphManyRelationToAttach = MorphManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

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
                            'morphManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key' => $morphManyRelationToAttach->getKey()
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_updating_morph_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphManyRelationToAttach = MorphManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

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
                            'morphManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key' => $morphManyRelationToAttach->getKey(),
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $morphManyRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_creating_morph_one_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

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
                            'morphOneRelation' => [
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

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphOneRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_attaching_morph_one_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphOneRelationToAttach = MorphOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

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
                            'morphOneRelation' => [
                                'operation' => 'attach',
                                'key' => $morphOneRelationToAttach->getKey()
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

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphOneRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_updating_morph_one_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphOneRelationToAttach = MorphOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

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
                            'morphOneRelation' => [
                                'operation' => 'update',
                                'key' => $morphOneRelationToAttach->getKey(),
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
            $morphOneRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphOneRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_creating_morph_to_many_relation_with_unauthorized_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

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
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'create',
                                    'attributes' => [],
                                    'pivot' => [
                                        'unauthorized_field' => 20
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
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.morphToManyRelation.0.pivot']]);
    }

    public function test_creating_a_resource_with_creating_morph_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

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
                            'morphToManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->count(), 2
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->orderBy('id')->get()[0]->morph_to_many_pivot->number, 20
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->orderBy('id')->get()[1]->morph_to_many_pivot->number, 30
        );
    }

    public function test_creating_a_resource_with_creating_morph_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

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
                            'morphToManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_creating_multiple_morph_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

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
                            'morphToManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->count(), 2
        );
    }

    public function test_creating_a_resource_with_attaching_morph_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphToManyRelationToAttach = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

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
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key' => $morphToManyRelationToAttach->getKey()
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_updating_morph_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphToManyRelationToAttach = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

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
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key' => $morphToManyRelationToAttach->getKey(),
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $morphToManyRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphToManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_creating_morph_by_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphedByManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_creating_morphed_by_many_relation_with_unauthorized_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphedByManyRelation' => [
                                [
                                    'operation' => 'create',
                                    'attributes' => [],
                                    'pivot' => [
                                        'unauthorized_field' => 20
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
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.morphedByManyRelation.0.pivot']]);
    }

    public function test_creating_a_resource_with_creating_morphed_by_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphedByManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation()->count(), 2
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation[0]->morphed_by_many_pivot->number, 20
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation[1]->morphed_by_many_pivot->number, 30
        );
    }

    public function test_creating_a_resource_with_creating_multiple_morphed_by_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphedByManyRelation' => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation()->count(), 2
        );
    }

    public function test_creating_a_resource_with_attaching_morphed_by_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphedByManyRelationToAttach = MorphedByManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphedByManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key' => $morphedByManyRelationToAttach->getKey()
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation()->count(), 1
        );
    }

    public function test_creating_a_resource_with_updating_morphed_by_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $morphedByManyRelationToAttach = MorphedByManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

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
                            'morphedByManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key' => $morphedByManyRelationToAttach->getKey(),
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $morphedByManyRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->morphedByManyRelation()->count(), 1
        );
    }
}