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

class MutateUpdateOperationsTest extends TestCase
{
    public function test_updating_a_resource_using_not_authorized_field(): void
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
                        'attributes' => ['not_authorized_field' => true],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.attributes']]);
    }

    public function test_updating_a_resource_without_key(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'attributes' => ['not_authorized_field' => true],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.key']]);
    }

    public function test_updating_a_resource_with_no_required_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => ['string' => 'string', 'name' => 'name', 'number' => 1],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation']]);
    }

    public function test_updating_a_resource_with_prohibited_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => ['string' => 'string', 'name' => 'name', 'number' => 1],
                        'relations'  => [
                            'belongsToRelation' => [
                                'operation'  => 'create',
                                'attributes' => [],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToRelation']]);
    }

    public function test_updating_a_resource_with_no_required_relation_but_empty_array(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => ['string' => 'string', 'name' => 'name', 'number' => 1],
                        'relations'  => [
                            'belongsToManyRelation' => [],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation']]);
    }

    public function test_updating_a_resource_with_required_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
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
                            'belongsToManyRelation' => [
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

        $this->assertDatabaseHas(
            $modelToUpdate->getTable(),
            [
                'name'   => 'new name',
                'number' => 5001,
            ]
        );
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
                        'key'       => $modelToUpdate->getKey(),
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                    'pivot'      => [
                                        'number' => true,
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToRelation' => [
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToRelation' => [
                                'operation' => 'attach',
                                'key'       => $belongsToRelationToAttach->getKey(),
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToRelation' => [
                                'operation' => 'detach',
                                'key'       => $belongsToRelationToDetach->getKey(),
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToRelation' => [
                                'operation'  => 'update',
                                'key'        => $belongsToRelationToUpdate->getKey(),
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            2
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $hasManyRelationToAttach->getKey(),
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_attaching_multiple_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToAttach1 = HasManyRelationFactory::new()
            ->createOne();
        $hasManyRelationToAttach2 = HasManyRelationFactory::new()
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

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
                            'hasManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'keys'      => [$hasManyRelationToAttach1->getKey(), $hasManyRelationToAttach2->getKey()],
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            2
        );
    }

    public function test_updating_a_resource_with_attaching_has_many_relation_with_required_rules(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToAttach = HasManyRelationFactory::new()
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'create',
                                    'attributes' => [],
                                ],
                            ],
                            'hasManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $hasManyRelationToAttach->getKey(),
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key'       => $hasManyRelationToDetach->getKey(),
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            0
        );
    }

    public function test_updating_a_resource_with_detaching_multiple_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToDetach1 = HasManyRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();
        $hasManyRelationToDetach2 = HasManyRelationFactory::new()
            ->for($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

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
                            'hasManyRelation' => [
                                [
                                    'operation'  => 'detach',
                                    'keys'       => [$hasManyRelationToDetach1->getKey(), $hasManyRelationToDetach2->getKey()],
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
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            0
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $hasManyRelationToAttach->getKey(),
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
            $hasManyRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_updating_multiple_has_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $hasManyRelationToAttach1 = HasManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();
        $hasManyRelationToAttach2 = HasManyRelationFactory::new()
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'keys'       => [$hasManyRelationToAttach1->getKey(), $hasManyRelationToAttach2->getKey()],
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
            $hasManyRelationToAttach1->fresh()->number,
            5001
        );

        $this->assertEquals(
            $hasManyRelationToAttach2->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasManyRelation()->count(),
            2
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneRelation' => [
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
            Model::find($response->json('updated.0'))->hasOneRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation' => 'attach',
                                'key'       => $hasOneRelationToAttach->getKey(),
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
            Model::find($response->json('updated.0'))->hasOneRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation' => 'detach',
                                'key'       => $hasOneRelationToDetach->getKey(),
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
            Model::find($response->json('updated.0'))->hasOneRelation()->count(),
            0
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation'  => 'update',
                                'key'        => $hasOneRelationToAttach->getKey(),
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
            $hasOneRelationToAttach->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
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
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
                                'operation' => 'attach',
                                'key'       => $hasOneOfManyRelationToAttach->getKey(),
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
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
                                'operation' => 'detach',
                                'key'       => $hasOneOfManyRelationToDetach->getKey(),
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
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(),
            0
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'hasOneOfManyRelation' => [
                                'operation'  => 'update',
                                'key'        => $hasOneOfManyRelationToUpdate->getKey(),
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
            $hasOneOfManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->hasOneOfManyRelation()->count(),
            1
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_toggling_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyToggled = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotToggled = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManyToggled);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'toggle',
                                    'key'        => $belongsToManyToggled->getKey(),
                                ],
                                [
                                    'operation'  => 'toggle',
                                    'key'        => $belongsToManyNotToggled->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->first()->getKey(),
            $belongsToManyNotToggled->getKey()
        );
    }

    public function test_updating_a_resource_with_toggling_multiple_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyToggled1 = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyToggled2 = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotToggled = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach([$belongsToManyToggled1, $belongsToManyToggled2]);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'toggle',
                                    'keys'       => [$belongsToManyToggled1->getKey(), $belongsToManyToggled2->getKey()],
                                ],
                                [
                                    'operation'  => 'toggle',
                                    'key'        => $belongsToManyNotToggled->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->first()->getKey(),
            $belongsToManyNotToggled->getKey()
        );
    }

    public function test_updating_a_resource_with_toggling_belongs_to_many_relation_and_updating_attributes_and_pivot(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyToggled = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotToggled = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManyToggled);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'toggle',
                                    'key'        => $belongsToManyToggled->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                    'pivot'      => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation'  => 'toggle',
                                    'key'        => $belongsToManyNotToggled->getKey(),
                                    'attributes' => ['number' => 5002], // 5002 because with factory it can't exceed 5000
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );

        $this->assertDatabaseHas(
            $belongsToManyToggled->getTable(),
            ['number' => 5001]
        );
        $this->assertDatabaseHas(
            $belongsToManyToggled->getTable(),
            ['number' => 5002]
        );

        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->first()->getKey(),
            $belongsToManyNotToggled->getKey()
        );
    }

    public function test_updating_a_resource_with_syncing_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManySynced = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotSynced = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManySynced);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $belongsToManyNotSynced->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );

        $this->assertDatabaseHas(
            $belongsToManySynced->getTable(),
            ['number' => 5001]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 20]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->first()->getKey(),
            $belongsToManyNotSynced->getKey()
        );
    }

    public function test_updating_a_resource_with_syncing_multiple_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManySynced = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotSynced1 = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotSynced2 = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach([$belongsToManySynced]);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'keys'       => [$belongsToManyNotSynced1->getKey(), $belongsToManyNotSynced2->getKey()],
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
        );

        $this->assertDatabaseHas(
            $belongsToManySynced->getTable(),
            ['number' => 5001]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 20]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->first()->getKey(),
            $belongsToManyNotSynced1->getKey()
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->get()->last()->getKey(),
            $belongsToManyNotSynced2->getKey()
        );
    }

    public function test_updating_a_resource_with_syncing_belongs_to_many_relation_without_detaching(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyToSync1 = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyToSync2 = BelongsToManyRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $belongsToManyToSync1->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                    'pivot'      => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation'         => 'sync',
                                    'key'               => $belongsToManyToSync2->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
        );

        $this->assertDatabaseHas(
            $belongsToManyToSync1->getTable(),
            ['number' => 5001]
        );
        $this->assertDatabaseHas(
            $belongsToManyToSync2->getTable(),
            ['number' => 5002]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 20]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->pluck('id')->toArray(),
            [
                $belongsToManyToSync1->getKey(),
                $belongsToManyToSync2->getKey(),
            ]
        );
    }

    public function test_updating_a_resource_with_syncing_belongs_to_many_relation_without_detaching_with_already_attached(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManySynced = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotSynced = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManySynced);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'         => 'sync',
                                    'key'               => $belongsToManyNotSynced->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
        );

        $this->assertDatabaseHas(
            $belongsToManyNotSynced->getTable(),
            ['number' => 5002]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->pluck('id')->toArray(),
            [
                $belongsToManySynced->getKey(),
                $belongsToManyNotSynced->getKey(),
            ]
        );
    }

    public function test_updating_a_resource_with_syncing_belongs_to_many_relation_with_detaching_with_already_attached(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManySynced = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotSynced = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManySynced);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $belongsToManyNotSynced->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );

        $this->assertDatabaseHas(
            $belongsToManyNotSynced->getTable(),
            ['number' => 5002]
        );
        $this->assertDatabaseHas(
            $modelToUpdate->belongsToManyRelation()->getTable(),
            ['number' => 21]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->pluck('id')->toArray(),
            [
                $belongsToManyNotSynced->getKey(),
            ]
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[1]->belongs_to_many_pivot->number,
            30
        );
    }

    public function test_updating_a_resource_with_updating_belongs_to_many_relation_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        $belongsToManyToUpdate = BelongsToManyRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $belongsToManyToUpdate->getKey(),
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
            [$modelToUpdate]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number,
            20
        );
    }

    public function test_updating_a_resource_with_updating_multiple_belongs_to_many_relation_pivot_fields(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();

        $belongsToManyToUpdate1 = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyToUpdate2 = BelongsToManyRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'keys'       => [$belongsToManyToUpdate1->getKey(), $belongsToManyToUpdate2->getKey()],
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
            [$modelToUpdate]
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation[1]->belongs_to_many_pivot->number,
            20
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $belongsToManyRelationToAttach->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );
    }

    public function test_updating_a_resource_with_attaching_multiple_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyRelationToAttach1 = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();
        $belongsToManyRelationToAttach2 = BelongsToManyRelationFactory::new()
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'keys'      => [$belongsToManyRelationToAttach1->getKey(), $belongsToManyRelationToAttach2->getKey()],
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            2
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'key'       => $belongsToManyRelationToDetach->getKey(),
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            0
        );
    }

    public function test_updating_a_resource_with_detaching_multiple_belongs_to_many_relation(): void
    {
        $modelToUpdate = ModelFactory::new()->createOne();
        $belongsToManyRelationToDetach1 = BelongsToManyRelationFactory::new()
            ->recycle($modelToUpdate)
            ->createOne();
        $belongsToManyRelationToDetach2 = BelongsToManyRelationFactory::new()
            ->recycle($modelToUpdate)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'detach',
                                    'keys'      => [$belongsToManyRelationToDetach1->getKey(), $belongsToManyRelationToDetach2->getKey()],
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
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            0
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
                        'operation'  => 'update',
                        'key'        => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $belongsToManyRelationToUpdate->getKey(),
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
            $belongsToManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('updated.0'))->belongsToManyRelation()->count(),
            1
        );
    }
}
