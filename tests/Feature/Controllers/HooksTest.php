<?php

namespace Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\SoftDeletedModelResource;

class HooksTest extends TestCase
{
    public function test_details_hook(): void
    {
        SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->get(
            '/api/model-hooks',
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            true,
            Cache::get('before-details')
        );
    }

    public function test_search_hook(): void
    {
        SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/search',
            [],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            true,
            Cache::get('before-search')
        );

        $this->assertEquals(
            true,
            Cache::get('after-search')
        );
    }

    public function test_mutate_hook(): void
    {
        $modelToCreate = SoftDeletedModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
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

        $this->assertEquals(
            true,
            Cache::get('before-mutate')
        );

        $this->assertEquals(
            true,
            Cache::get('after-mutate')
        );
    }

    public function test_operate_hook(): void
    {
        SoftDeletedModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/actions/modify-number',
            [],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            true,
            Cache::get('before-operate')
        );

        $this->assertEquals(
            true,
            Cache::get('after-operate')
        );
    }

    public function test_destroy_hook(): void
    {
        $model = SoftDeletedModelFactory::new()->count(1)->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->delete(
            '/api/model-hooks',
            [
                'resources' => [$model->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            true,
            Cache::get('before-destroy')
        );

        $this->assertEquals(
            true,
            Cache::get('after-destroy')
        );
    }

    public function test_restore_hook(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/restore',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            true,
            Cache::get('before-restore')
        );

        $this->assertEquals(
            true,
            Cache::get('after-restore')
        );
    }

    public function test_force_destroy_hook(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $this->delete(
            '/api/model-hooks/force',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertEquals(
            true,
            Cache::get('before-force-destroy')
        );

        $this->assertEquals(
            true,
            Cache::get('after-force-destroy')
        );
    }

    public function test_resource_mutating_hook_by_creating(): void
    {
        $modelToCreate = SoftDeletedModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
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
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelsToCreate[0]->name,
                            'number' => $modelsToCreate[0]->number,
                        ],
                    ],
                    [
                        'operation'  => 'create',
                        'attributes' => [
                            'name'   => $modelsToCreate[1]->name,
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
                        'operation'  => 'update',
                        'key'        => $modelsToUpdate[0]->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
                            'number' => 5001,
                        ],
                    ],
                    [
                        'operation'  => 'update',
                        'key'        => $modelsToUpdate[1]->getKey(),
                        'attributes' => [
                            'name'   => 'new name',
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

    public function test_resource_mutating_hook_by_attaching(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();
        $belongsToRelationToAttach = BelongsToRelationFactory::new()->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
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

        $this->assertEquals(
            1,
            Cache::get('mutating-belongs-to')
        );

        $this->assertEquals(
            1,
            Cache::get('mutated-belongs-to')
        );
    }

    public function test_resource_mutating_hook_by_detaching(): void
    {
        $belongsToRelationToDetach = BelongsToRelationFactory::new()
            ->createOne();

        $modelToUpdate = SoftDeletedModelFactory::new()
            ->for($belongsToRelationToDetach)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
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

        $this->assertEquals(
            1,
            Cache::get('mutating-belongs-to')
        );

        $this->assertEquals(
            1,
            Cache::get('mutated-belongs-to')
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

        $this->assertEquals(
            1,
            Cache::get('mutating-belongs-to-many')
        );

        $this->assertEquals(
            1,
            Cache::get('mutated-belongs-to-many')
        );
    }

    public function test_resource_mutating_hook_by_toggling(): void
    {
        $modelToUpdate = SoftDeletedModelFactory::new()->createOne();
        $belongsToManyToggled = BelongsToManyRelationFactory::new()->createOne();
        $belongsToManyNotToggled = BelongsToManyRelationFactory::new()->createOne();

        $modelToUpdate->belongsToManyRelation()
            ->attach($belongsToManyToggled);

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $this->post(
            '/api/model-hooks/mutate',
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

        $this->assertEquals(
            2,
            Cache::get('mutating-belongs-to-many')
        );

        $this->assertEquals(
            2,
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
                    $softDeletedModels[1]->getKey()
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
