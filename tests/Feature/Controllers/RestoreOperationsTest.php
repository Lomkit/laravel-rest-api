<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\RedPolicy;
use Lomkit\Rest\Tests\Support\Policies\RedPolicyButForModel;
use Lomkit\Rest\Tests\Support\Rest\Resources\SoftDeletedModelResource;

class RestoreOperationsTest extends TestCase
{
    public function test_restoring_a_non_authorized_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, RedPolicy::class);

        $response = $this->post(
            '/api/soft-deleted-models/restore',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_restoring_a_non_authorized_model_with_an_authorized_one(): void
    {
        $model = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();
        $modelRestorable = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        RedPolicyButForModel::forModel($modelRestorable);
        Gate::policy(SoftDeletedModel::class, RedPolicyButForModel::class);

        $response = $this->post(
            '/api/soft-deleted-models/restore',
            [
                'resources' => [$model->getKey(), $modelRestorable->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
        $this->assertNotEquals(
            null,
            $modelRestorable->fresh()->deleted_at,
        );
        $this->assertNotEquals(
            null,
            $model->fresh()->deleted_at,
        );
    }

    public function test_restoring_a_soft_deleted_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->post(
            '/api/soft-deleted-models/restore',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel], new SoftDeletedModelResource());
        $this->assertDatabaseHas('soft_deleted_models', [
            'id'         => $softDeletedModel->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_restoring_multiple_soft_deleted_models(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();
        $softDeletedModel2 = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->post(
            '/api/soft-deleted-models/restore',
            [
                'resources' => [$softDeletedModel->getKey(), $softDeletedModel2->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel, $softDeletedModel2], new SoftDeletedModelResource());
        $this->assertDatabaseHas('soft_deleted_models', [
            'id'         => $softDeletedModel->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('soft_deleted_models', [
            'id'         => $softDeletedModel2->getKey(),
            'deleted_at' => null,
        ]);
    }
}
