<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class HooksTest extends TestCase
{
    public function test_resource_mutating_hook_by_creating(): void
    {
        $modelToCreate = SoftDeletedModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelToCreate->name,
                            'number' => $modelToCreate->number,
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            1,
            Cache::get('mutating')
        );

        $this->assertEquals(
            1,
            Cache::get('mutated')
        );
    }

    public function test_resource_mutating_hook_by_creating_two_entries(): void
    {
        $modelsToCreate = SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelsToCreate[0]->name,
                            'number' => $modelsToCreate[0]->number,
                        ],
                    ],
                    [
                        'operation' => 'create',
                        'attributes' => [
                            'name' => $modelsToCreate[1]->name,
                            'number' => $modelsToCreate[1]->number,
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            2,
            Cache::get('mutating')
        );

        $this->assertEquals(
            2,
            Cache::get('mutated')
        );
    }

    public function test_resource_mutating_hook_by_updating(): void
    {
        $modelToUpdate = SoftDeletedModelFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001,
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            1,
            Cache::get('mutating')
        );

        $this->assertEquals(
            1,
            Cache::get('mutated')
        );
    }

    public function test_resource_mutating_hook_by_updating_two_entries(): void
    {
        $modelsToUpdate = SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelsToUpdate[0]->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001,
                        ],
                    ],
                    [
                        'operation' => 'update',
                        'key' => $modelsToUpdate[1]->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001,
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            2,
            Cache::get('mutating')
        );

        $this->assertEquals(
            2,
            Cache::get('mutated')
        );
    }

    public function test_resource_mutating_hook_by_syncing(): void
    {
        $modelToUpdate = SoftDeletedModelFactory::new()->createOne();
        $belongsToManyNotSynced = BelongsToManyRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
            [
                'mutate' => [
                    [
                        'operation' => 'update',
                        'key' => $modelToUpdate->getKey(),
                        'attributes' => [
                            'name' => 'new name',
                            'number' => 5001,
                        ],
                        'relations' => [
                            'belongsToManyRelation' => [
                                [
                                    'operation' => 'sync',
                                    'key' => $belongsToManyNotSynced->getKey(),
                                    'attributes' => ['number' => 5001], // 5001 because with factory it can't exceed 5000
                                    'pivot' => [
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

        $this->assertEquals(
            1,
            Cache::get('mutating-belongs-to-many')
        );

        $this->assertEquals(
            1,
            Cache::get('mutated-belongs-to-many')
        );
    }

    public function test_resource_destroying(): void
    {
        $models = SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->delete(
            '/api/model-hooks',
            [
                'resources' => [
                    $models[0]->getKey(),
                    $models[1]->getKey(),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            2,
            Cache::get('destroying')
        );

        $this->assertEquals(
            2,
            Cache::get('destroyed')
        );
    }

    public function test_resource_restoring_two_models(): void
    {
        $softDeletedModels = SoftDeletedModelFactory::new()->count(2)->trashed()->create();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/restore',
            [
                'resources' => [
                    $softDeletedModels[0]->getKey(),
                    $softDeletedModels[1]->getKey(),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            2,
            Cache::get('restoring')
        );

        $this->assertEquals(
            2,
            Cache::get('restored')
        );
    }

    public function test_resource_force_destroying_two_models(): void
    {
        $softDeletedModels = SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $this->delete(
            '/api/model-hooks/force',
            [
                'resources' => [
                    $softDeletedModels[0]->getKey(),
                    $softDeletedModels[1]->getKey(),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            2,
            Cache::get('force-destroying')
        );

        $this->assertEquals(
            2,
            Cache::get('force-destroyed')
        );
    }
}
