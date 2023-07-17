<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\RedPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
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
                'resources' => [$softDeletedModel->getKey()]
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_restoring_a_soft_deleted_model(): void
    {
        $softDeletedModel = SoftDeletedModelFactory::new()->count(1)->trashed()->createOne();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        $response = $this->post(
            '/api/soft-deleted-models/restore',
            [
                'resources' => [$softDeletedModel->getKey()]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel], new SoftDeletedModelResource);
        $this->assertDatabaseHas('soft_deleted_models', [
            'id' => $softDeletedModel->getKey(),
            'deleted_at' => null
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
                'resources' => [$softDeletedModel->getKey(), $softDeletedModel2->getKey()]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$softDeletedModel, $softDeletedModel2], new SoftDeletedModelResource);
        $this->assertDatabaseHas('soft_deleted_models', [
            'id' => $softDeletedModel->getKey(),
            'deleted_at' => null
        ]);
        $this->assertDatabaseHas('soft_deleted_models', [
            'id' => $softDeletedModel2->getKey(),
            'deleted_at' => null
        ]);
    }
}