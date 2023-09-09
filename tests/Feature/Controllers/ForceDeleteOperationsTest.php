<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\RedPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\SoftDeletedModelResource;

class ForceDeleteOperationsTest extends TestCase
{
    public function test_force_deleting_a_non_authorized_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, RedPolicy::class);

        $response = $this->delete(
            '/api/soft-deleted-models/force',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_force_deleting_a_soft_deleted_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/soft-deleted-models/force',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel], new SoftDeletedModelResource());
        $this->assertDatabaseMissing('soft_deleted_models', [
            'id' => $softDeletedModel->getKey(),
        ]);
    }

    public function test_force_deleting_a_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/soft-deleted-models/force',
            [
                'resources' => [$softDeletedModel->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel], new SoftDeletedModelResource());
        $this->assertDatabaseMissing('soft_deleted_models', [
            'id' => $softDeletedModel->getKey(),
        ]);
    }

    public function test_force_deleting_multiple_models(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->createOne();
        $softDeletedModel2 = SoftDeletedModelFactory::new()->count(1)->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->delete(
            '/api/soft-deleted-models/force',
            [
                'resources' => [$softDeletedModel->getKey(), $softDeletedModel2->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel, $softDeletedModel2], new SoftDeletedModelResource());
        $this->assertDatabaseMissing('soft_deleted_models', [
            'id' => $softDeletedModel->getKey(),
        ]);
        $this->assertDatabaseMissing('soft_deleted_models', [
            'id' => $softDeletedModel2->getKey(),
        ]);
    }
}
