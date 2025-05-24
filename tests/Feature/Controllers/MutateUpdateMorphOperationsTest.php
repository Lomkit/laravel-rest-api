<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphedByManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphManyRelationFactory;
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

class MutateUpdateMorphOperationsTest extends TestCase
{
    public function test_updating_a_resource_using_field_not_following_custom_rules(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => ['string' => true],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.attributes.string']]);
    }

    public function test_updating_a_resource_with_creating_morph_to_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToRelation' => [
                                'operation'  => 'create',
                                'attributes' => [],
                            ],
                        ],
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
                'name'   => 'new name',
                'number' => 5001,
            ]
        );

        $this->assertNotNull(Model::first()->morph_to_relation_id);
        $this->assertNotNull(Model::first()->morph_to_relation_type);
    }

    public function test_updating_a_resource_with_attaching_morph_to_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToRelationToAttach = MorphToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToRelation' => [
                                'operation' => 'attach',
                                'key'       => $morphToRelationToAttach->getKey(),
                            ],
                        ],
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
            $morphToRelationToAttach->getKey(),
            Model::find($response->json('updated.0'))->morph_to_relation_id
        );
    }

    public function test_updating_a_resource_with_detaching_morph_to_relation(): void
    {
        $morphToRelationToDetach = MorphToRelationFactory::new()
            ->createOne();
        $modelToUpdate = ModelFactory::new()
            ->for($morphToRelationToDetach)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToRelation' => [
                                'operation' => 'detach',
                                'key'       => $morphToRelationToDetach->getKey(),
                            ],
                        ],
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

        $this->assertNull(Model::find($response->json('updated.0'))->morph_to_relation_id);
        $this->assertNull(Model::find($response->json('updated.0'))->morph_to_relation_type);
    }

    public function test_updating_a_resource_with_updating_morph_to_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToRelationToUpdate = MorphToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToRelation' => [
                                'operation'  => 'update',
                                'key'        => $morphToRelationToUpdate->getKey(),
                                'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                            ],
                        ],
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
            $morphToRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morph_to_relation_id,
            $morphToRelationToUpdate->getKey()
        );
    }

    public function test_updating_a_resource_with_creating_morph_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_creating_multiple_morph_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphManyRelation()->count(),
            2
        );
    }

    public function test_updating_a_resource_with_attaching_morph_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $morphManyRelationToAttach->getKey(),
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_detaching_morph_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphManyRelationToDetach = MorphManyRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key'       => $morphManyRelationToDetach->getKey(),
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphManyRelation()->count(),
            0
        );
    }

    public function test_updating_a_resource_with_updating_morph_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $morphManyRelationToAttach->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                ],
                            ],
                        ],
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
            $morphManyRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_creating_morph_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphOneRelation' => [
                                'operation'  => 'create',
                                'attributes' => [],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphOneRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_attaching_morph_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphOneRelation' => [
                                'operation' => 'attach',
                                'key'       => $morphOneRelationToAttach->getKey(),
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphOneRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_detaching_morph_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphOneRelationToDetach = MorphOneRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphOneRelation' => [
                                'operation' => 'detach',
                                'key'       => $morphOneRelationToDetach->getKey(),
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphOneRelation()->count(),
            0
        );
    }

    public function test_updating_a_resource_with_updating_morph_one_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphOneRelationToUpdate = MorphOneRelationFactory::new()
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphOneRelation' => [
                                'operation'  => 'update',
                                'key'        => $morphOneRelationToUpdate->getKey(),
                                'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                            ],
                        ],
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
            $morphOneRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphOneRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_creating_morph_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_creating_morph_to_many_relation_with_unauthorized_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'unauithorized_field' => 20,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.relations.morphToManyRelation.0.pivot']]);
    }

    public function test_updating_a_resource_with_creating_morph_to_many_relation_with_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'number' => 30,
                                    ],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->orderBy('id')->get()[0]->morph_to_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->orderBy('id')->get()[1]->morph_to_many_pivot->number,
            30
        );
    }

    public function test_updating_a_resource_with_creating_multiple_morph_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            2
        );
    }

    public function test_updating_a_resource_with_attaching_morph_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $morphToManyRelationToAttach->getKey(),
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_detaching_morph_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToManyRelationToDetach = MorphToManyRelationFactory::new()
            ->recycle($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key'       => $morphToManyRelationToDetach->getKey(),
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            0
        );
    }

    public function test_updating_a_resource_with_updating_morph_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToManyRelationToUpdate = MorphToManyRelationFactory::new()
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $morphToManyRelationToUpdate->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                ],
                            ],
                        ],
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
            $morphToManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_creating_morphed_by_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphedByManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_creating_morphed_by_many_relation_with_unauthorized_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'unauthorized_field' => 20,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.relations.morphedByManyRelation.0.pivot']]);
    }

    public function test_updating_a_resource_with_creating_morphed_by_many_relation_with_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'number' => 30,
                                    ],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphedByManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphedByManyRelation[0]->morphed_by_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphedByManyRelation[1]->morphed_by_many_pivot->number,
            30
        );
    }

    public function test_updating_a_resource_with_creating_multiple_morphed_by_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphedByManyRelation()->count(),
            2
        );
    }

    public function test_updating_a_resource_with_attaching_morphed_by_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $morphedByManyRelationToAttach->getKey(),
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphedByManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_detaching_morphed_by_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphedByManyRelationToDetach = MorphedByManyRelationFactory::new()
            ->recycle($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphedByManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key'       => $morphedByManyRelationToDetach->getKey(),
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphedByManyRelation()->count(),
            0
        );
    }

    public function test_updating_a_resource_with_updating_morphed_by_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphedByManyRelationToUpdate = MorphedByManyRelationFactory::new()
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphedByManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $morphedByManyRelationToUpdate->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                ],
                            ],
                        ],
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
            $morphedByManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphedByManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_syncing_morph_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToManySynced = MorphToManyRelationFactory::new()->createOne();
        $morphToManyNotSynced = MorphToManyRelationFactory::new()->createOne();

        $modelToUpdate->morphToManyRelation()
            ->attach($morphToManySynced);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $morphToManyNotSynced->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                    'pivot'      => [
                                        'number' => 20,
                                    ],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            1
        );

        $this->assertDatabaseHas(
            $morphToManySynced->getTable(),
            ['number' => 5001]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->morphToManyRelation()->getTable(),
            ['number' => 20]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->first()->getKey(),
            $morphToManyNotSynced->getKey()
        );
    }

    public function test_updating_a_resource_with_syncing_morph_to_many_relation_without_detaching(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToManyToSync1 = MorphToManyRelationFactory::new()->createOne();
        $morphToManyToSync2 = MorphToManyRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $morphToManyToSync1->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                    'pivot'      => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation'         => 'sync',
                                    'key'               => $morphToManyToSync2->getKey(),
                                    'without_detaching' => true,
                                    'attributes'        => ['number' => 5002], // 5001 because with factory it can't exceed 5000
                                    'pivot'             => [
                                        'number' => 21,
                                    ],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            2
        );

        $this->assertDatabaseHas(
            $morphToManyToSync1->getTable(),
            ['number' => 5001]
        );
        $this->assertDatabaseHas(
            $morphToManyToSync2->getTable(),
            ['number' => 5002]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->morphToManyRelation()->getTable(),
            ['number' => 20]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->morphToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->pluck('id')->toArray(),
            [
                $morphToManyToSync1->getKey(),
                $morphToManyToSync2->getKey(),
            ]
        );
    }

    public function test_updating_a_resource_with_syncing_morph_to_many_relation_without_detaching_with_already_attached(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToManySynced = MorphToManyRelationFactory::new()->createOne();
        $morphToManyNotSynced = MorphToManyRelationFactory::new()->createOne();

        $modelToUpdate->morphToManyRelation()
            ->attach($morphToManySynced);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'         => 'sync',
                                    'key'               => $morphToManyNotSynced->getKey(),
                                    'without_detaching' => true,
                                    'attributes'        => ['number' => 5002], // 5001 because with factory it can't exceed 5000
                                    'pivot'             => [
                                        'number' => 21,
                                    ],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            2
        );

        $this->assertDatabaseHas(
            $morphToManyNotSynced->getTable(),
            ['number' => 5002]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->morphToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->pluck('id')->toArray(),
            [
                $morphToManySynced->getKey(),
                $morphToManyNotSynced->getKey(),
            ]
        );
    }

    public function test_updating_a_resource_with_syncing_morph_to_many_relation_with_detaching_with_already_attached(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $morphToManySynced = MorphToManyRelationFactory::new()->createOne();
        $morphToManyNotSynced = MorphToManyRelationFactory::new()->createOne();

        $modelToUpdate->morphToManyRelation()
            ->attach($morphToManySynced);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $morphToManyNotSynced->getKey(),
                                    'attributes' => ['number' => 5002], // 5001 because with factory it can't exceed 5000
                                    'pivot'      => [
                                        'number' => 21,
                                    ],
                                ],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphToManyRelation()->count(),
            1
        );

        $this->assertDatabaseHas(
            $morphToManyNotSynced->getTable(),
            ['number' => 5002]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->morphToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->morphToManyRelation()->pluck('id')->toArray(),
            [
                $morphToManyNotSynced->getKey(),
            ]
        );
    }

    public function test_updating_a_resource_with_creating_morph_one_of_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'morphOneOfManyRelation' => [
                                'operation'  => 'create',
                                'attributes' => [],
                            ],
                        ],
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
            Model::find($response->json('updated.0'))->morphOneOfManyRelation()->count(),
            1
        );
    }
}
