<?php

namespace Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\NoRelationshipAuthorizationModelFactory;
use Lomkit\Rest\Tests\Support\Models\MorphManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphOneRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToRelation;
use Lomkit\Rest\Tests\Support\Models\NoRelationshipAuthorizedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\NoRelationshipAuthorizationModelPolicy;

class MutateNotAuthorizedMorphsRelationsOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(NoRelationshipAuthorizedModel::class, NoRelationshipAuthorizationModelPolicy::class);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_morph_to_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToRelationToAttach = MorphToRelationFactory::new()->createOne();

        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_morph_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphManyRelationToAttach = MorphManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_morph_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphOneRelationToAttach = MorphOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_morph_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphOneOfManyRelationToAttach = MorphOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'morphOneOfManyRelation' => [
                                'operation' => 'attach',
                                'key'       => $morphOneOfManyRelationToAttach->getKey(),
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_morph_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToManyRelationToAttach = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_morph_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToManyRelationToAttach1 = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();
        $morphToManyRelationToAttach2 = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'attach',
                                    'key'       => $morphToManyRelationToAttach1->getKey(),
                                    'pivot'     => [
                                        'number' => 20,
                                    ],
                                ],
                                [
                                    'operation' => 'attach',
                                    'key'       => $morphToManyRelationToAttach2->getKey(),
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_morph_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_morph_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_morph_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(MorphOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_morph_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_multiple_not_authorized_morph_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_morph_to_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToRelationToUpdate = MorphToRelationFactory::new()->createOne();

        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_morph_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphManyRelationToUpdate = MorphManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'morphManyRelation' => [
                                [
                                    'operation'  => 'update',
                                    'key'        => $morphManyRelationToUpdate->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_morph_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphOneRelationToUpdate = MorphOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_morph_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphOneOfManyRelationToUpdate = MorphOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'morphOneOfManyRelation' => [
                                'operation'  => 'update',
                                'key'        => $morphOneOfManyRelationToUpdate->getKey(),
                                'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_morph_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToManyRelationToUpdate = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_morph_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToManyRelationToUpdate1 = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();
        $morphToManyRelationToUpdate2 = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'morphToManyRelation' => [
                                [
                                    'operation' => 'update',
                                    'key'       => $morphToManyRelationToUpdate1->getKey(),
                                    'pivot'     => [
                                        'number' => 20,
                                    ],
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                ],
                                [
                                    'operation' => 'update',
                                    'key'       => $morphToManyRelationToUpdate2->getKey(),
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_morph_to_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToRelationToDetach = MorphToRelationFactory::new()->createOne();

        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_morph_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphManyRelationToDetach = MorphManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_morph_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphOneRelationToDetach = MorphOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_morph_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphOneOfManyRelationToDetach = MorphOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(MorphOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'morphOneOfManyRelation' => [
                                'operation' => 'detach',
                                'key'       => $morphOneOfManyRelationToDetach->getKey(),
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_morph_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $morphToManyRelationToDetach = MorphToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelToCreate->name,
                            'number' => $modelToCreate->number,
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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_toggling_linked_not_authorized_morph_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $morphToManyToggled = MorphToManyRelationFactory::new()->createOne();

        $modelToUpdate->morphToManyRelation()
            ->attach($morphToManyToggled);

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
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
                                    'operation'  => 'toggle',
                                    'key'        => $morphToManyToggled->getKey(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_toggling_not_linked_not_authorized_morph_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $morphToManyNotToggled = MorphToManyRelationFactory::new()->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
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
                                    'operation'  => 'toggle',
                                    'key'        => $morphToManyNotToggled->getKey(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_syncing_linked_not_authorized_morph_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $morphToManySynced = MorphToManyRelationFactory::new()->createOne();
        $morphToManyNotSynced = MorphToManyRelationFactory::new()->createOne();

        $modelToUpdate->morphToManyRelation()
            ->attach($morphToManySynced);

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_syncing_not_linked_not_authorized_morph_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $morphToManyNotSynced = MorphToManyRelationFactory::new()->createOne();

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_syncing_already_linked_not_authorized_morph_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $morphToManySynced = MorphToManyRelationFactory::new()->createOne();

        $modelToUpdate->morphToManyRelation()
            ->attach($morphToManySynced);

        Gate::policy(MorphToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
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
                                    'key'        => $morphToManySynced->getKey(),
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

        $response->assertStatus(200);
    }
}
