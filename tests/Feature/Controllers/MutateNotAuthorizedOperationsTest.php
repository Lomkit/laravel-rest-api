<?php

namespace Controllers;

use Illuminate\Support\Facades\Gate;
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

class MutateNotAuthorizedOperationsTest extends TestCase
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
}
