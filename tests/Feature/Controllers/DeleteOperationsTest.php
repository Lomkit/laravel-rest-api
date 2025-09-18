<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\RedPolicy;
use Lomkit\Rest\Tests\Support\Policies\RedPolicyButForModel;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\SoftDeletedModelResource;

class DeleteOperationsTest extends TestCase
{
    public function test_deleting_a_non_authorized_model(): void
    {
        $model = ModelFactory::new()->count(1)->createOne();

        Gate::policy(Model::class, RedPolicy::class);

        $response = $this->delete(
            '/api/models',
            [
                'resources' => [$model->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_deleting_a_non_authorized_model_with_an_authorized_one(): void
    {
        $model = ModelFactory::new()->count(1)->createOne();
        $modelDeletable = ModelFactory::new()->count(1)->createOne();

        RedPolicyButForModel::forModel($modelDeletable);
        Gate::policy(Model::class, RedPolicyButForModel::class);

        $response = $this->delete(
            '/api/models',
            [
                'resources' => [$model->getKey(), $modelDeletable->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
        $this->assertDatabaseHas('models', $model->only('id'));
        $this->assertDatabaseHas('models', $modelDeletable->only('id'));
    }

    public function test_deleting_a_not_existing_model(): void
    {
        $model = ModelFactory::new()->count(1)->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/models',
            [
                'resources' => [
                    'undefined-id',
                    $model->getKey()
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['resources.0']]);
        $this->assertDatabaseHas('models', $model->only('id'));
    }

    public function test_deleting_a_model(): void
    {
        $model = ModelFactory::new()->count(1)->createOne();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/models',
            [
                'resources' => [$model->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$model], new ModelResource());
        $this->assertDatabaseMissing('models', $model->only('id'));
    }

    public function test_deleting_a_soft_deleted_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/soft-deleted-models',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel], new SoftDeletedModelResource());
        $this->assertSoftDeleted($softDeletedModel);
    }

    public function test_deleting_multiple_soft_deleted_models(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->createOne();
        $softDeletedModel2 = SoftDeletedModelFactory::new()->count(1)->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/soft-deleted-models',
            [
                'resources' => [$softDeletedModel->getKey(), $softDeletedModel2->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel, $softDeletedModel2], new SoftDeletedModelResource());
        $this->assertSoftDeleted($softDeletedModel);
        $this->assertSoftDeleted($softDeletedModel2);
    }
}
