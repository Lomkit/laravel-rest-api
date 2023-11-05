<?php

namespace Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\SoftDeletedModelResource;

class HooksTest extends TestCase
{
    public function test_details_hook(): void
    {
        ModelFactory::new()->count(2)->create();

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
        ModelFactory::new()->count(2)->create();

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
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
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
        ModelFactory::new()->count(2)->create();

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
        $model = ModelFactory::new()->count(1)->createOne();

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
}
