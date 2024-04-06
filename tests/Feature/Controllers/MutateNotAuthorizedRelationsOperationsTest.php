<?php

namespace Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\NoRelationshipAuthorizationModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\NoRelationshipAuthorizedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\NoRelationshipAuthorizationModelPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoRelationshipAuthorizationModelResource;
use Mockery;
use Mockery\MockInterface;

class MutateNotAuthorizedRelationsOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(NoRelationshipAuthorizedModel::class, NoRelationshipAuthorizationModelPolicy::class);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_belongs_to_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();

        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_has_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasManyRelationToAttach = HasManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_has_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasOneRelationToAttach = HasOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasOneRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_has_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasOneOfManyRelationToAttach = HasOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_belongs_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $belongsToManyRelationToAttach = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_attaching_not_authorized_belongs_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
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

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_has_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(HasManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_has_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(HasOneRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_has_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_not_authorized_belongs_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_creating_multiple_not_authorized_belongs_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_belongs_to_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $belongsToRelationToUpdate = BelongsToRelationFactory::new()->createOne();

        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/no-relationship-authorization-models/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                        'relations' => [
                            'belongsToRelation' => [
                                'operation' => 'update',
                                'key' => $belongsToRelationToUpdate->getKey(),
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

    public function test_creating_a_resource_with_updating_not_authorized_has_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasManyRelationToUpdate = HasManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_has_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasOneRelationToUpdate = HasOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasOneRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_has_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasOneOfManyRelationToUpdate = HasOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_belongs_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $belongsToManyRelationToUpdate = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_updating_not_authorized_belongs_to_many_relation_with_pivot_fields(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
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

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_belongs_to_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $belongsToRelationToDetach = BelongsToRelationFactory::new()->createOne();

        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_has_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasManyRelationToDetach = HasManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_has_one_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasOneRelationToDetach = HasOneRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasOneRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_has_one_of_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $hasOneOfManyRelationToDetach = HasOneOfManyRelationFactory::new()
            ->for(
                ModelFactory::new()->createOne()
            )
            ->createOne();

        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_creating_a_resource_with_detaching_not_authorized_belongs_to_many_relation(): void
    {
        $modelToCreate = NoRelationshipAuthorizationModelFactory::new()->makeOne();
        $belongsToManyRelationToDetach = BelongsToManyRelationFactory::new()
            ->has(
                ModelFactory::new()->count(1)
            )
            ->createOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_toggling_linked_not_authorized_belongs_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $belongsToManyToggled = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManyToggled);

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'toggle',
                                    'key'        => $belongsToManyToggled->getKey(),
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

    public function test_updating_a_resource_with_toggling_not_linked_not_authorized_belongs_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $belongsToManyNotToggled = BelongsToManyRelationFactory::new()->createOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_syncing_linked_not_authorized_belongs_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $belongsToManySynced = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotSynced = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManySynced);

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_syncing_not_linked_not_authorized_belongs_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $belongsToManyNotSynced = BelongsToManyRelationFactory::new()->createOne();

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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

        $response->assertStatus(403);
    }

    public function test_updating_a_resource_with_syncing_already_linked_not_authorized_belongs_to_many_relation(): void
    {
        $modelToUpdate = NoRelationshipAuthorizationModelFactory::new()->createOne();
        $belongsToManySynced = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManySynced);

        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

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
                            'belongsToManyRelation' => [
                                [
                                    'operation'  => 'sync',
                                    'key'        => $belongsToManySynced->getKey(),
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
