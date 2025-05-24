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
                        'operation'  => 'create',
                        'attributes' => ['not_authorized_field' => true],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.attributes']]);
    }

    public function test_creating_a_resource_using_no_mutate_key(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate']]);
    }

    public function test_creating_a_resource_using_not_authorized_attach_root_operator(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'attach',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.operation', 'mutate.0.key']]);
    }

    public function test_creating_a_resource_using_not_authorized_detach_root_operator(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'detach',
                        'key' => 1
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.operation', 'mutate.0.key']]);
    }

    public function test_creating_a_resource_using_field_not_following_custom_rules(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => ['string' => true],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.attributes.string']]);
    }

    public function test_creating_a_resource_with_no_required_relation(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => ['string' => 'string', 'name' => 'name', 'number' => 1],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation']]);
    }

    public function test_creating_a_resource_with_prohibited_relation(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
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

    public function test_creating_a_resource_with_no_required_relation_but_empty_array(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
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
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation']]);
    }

    public function test_creating_a_resource_with_not_authorized_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'not_authorized_relation' => [
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

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.relations']]);
    }

    public function test_creating_a_resource_with_required_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/constrained/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );
    }

    public function test_creating_a_resource_using_pivot_field_not_following_custom_pivot_rules(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
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
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation.0.pivot.number']]);
    }

    public function test_creating_a_resource_with_unique_validation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne([
            'unique' => 'my unique string',
        ]);
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);
        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'unique'   => $modelToCreate->unique,
                            'name'     => $modelToCreate->name,
                            'number'   => $modelToCreate->number,
                        ],
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

    public function test_creating_a_resource(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        $this->assertNotNull(Model::first()->belongs_to_relation_id);
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        $this->assertEquals(
            $belongsToRelationToAttach->getKey(),
            Model::find($response->json('created.0'))->belongs_to_relation_id
        );
    }

    public function test_creating_a_resource_with_updating_belongs_to_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $belongsToRelationToUpdate = BelongsToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $belongsToRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongs_to_relation_id,
            $belongsToRelationToUpdate->getKey()
        );
    }

    public function test_creating_a_resource_with_creating_has_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_creating_multiple_has_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasManyRelation()->count(),
            2
        );
    }

    public function test_creating_a_resource_with_attaching_has_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_updating_has_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $hasManyRelationToUpdate = HasManyRelationFactory::new()
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'hasManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $hasManyRelationToUpdate->getKey(),
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $hasManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_creating_has_one_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasOneRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_attaching_has_one_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasOneRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_updating_has_one_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $hasOneRelationToUpdate = HasOneRelationFactory::new()
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'hasOneRelation' => [
                                'operation'  => 'update',
                                'key'        => $hasOneRelationToUpdate->getKey(),
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $hasOneRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasOneRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_creating_has_one_of_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasOneOfManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_attaching_has_one_of_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasOneOfManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_updating_has_one_of_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $hasOneOfManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->hasOneOfManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_creating_belongs_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_creating_belongs_to_many_relation_with_unauthorized_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
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
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate.0.relations.belongsToManyRelation.0.pivot']]);
    }

    public function test_creating_a_resource_with_creating_belongs_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation[1]->belongs_to_many_pivot->number,
            30
        );
    }

    public function test_creating_a_resource_with_creating_multiple_belongs_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            2
        );
    }

    public function test_creating_a_resource_with_attaching_belongs_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_attaching_belongs_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $belongsToManyRelationToAttach1->getKey(),
                                    'pivot'     => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation' => 'attach',
                                    'key'       => $belongsToManyRelationToAttach2->getKey(),
                                    'pivot'     => [
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
            [$modelToCreate],
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation[1]->belongs_to_many_pivot->number,
            30
        );
    }

    public function test_creating_a_resource_with_updating_belongs_to_many_relation(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $belongsToManyRelationToUpdate->fresh()->number,
            5001
        );

        // Here we test that the model is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            1
        );
    }

    public function test_creating_a_resource_with_updating_belongs_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $belongsToManyRelationToUpdate1 = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();
        $belongsToManyRelationToUpdate2 = BelongsToManyRelationFactory::new()
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key'       => $belongsToManyRelationToUpdate1->getKey(),
                                    'pivot'     => [
                                        'number' => 20,
                                    ],
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                ],
                                [
                                    'operation' => 'update',
                                    'key'       => $belongsToManyRelationToUpdate2->getKey(),
                                    'pivot'     => [
                                        'number' => 30,
                                    ],
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
            [$modelToCreate],
        );

        // Here we test that the number has been modified on the relation
        $this->assertEquals(
            $belongsToManyRelationToUpdate1->fresh()->number,
            5001
        );
        $this->assertEquals(
            $belongsToManyRelationToUpdate2->fresh()->number,
            5001
        );

        // Here we test that the relation is correctly linked
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation()->count(),
            2
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation[0]->belongs_to_many_pivot->number,
            20
        );
        $this->assertEquals(
            Model::find($response->json('created.0'))->belongsToManyRelation[1]->belongs_to_many_pivot->number,
            30
        );
    }
}
