<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\RedPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoAuthorizationResource;

class NoAuthorizationTest extends TestCase
{
    public function test_searching_with_global_authorization_disabled(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, RedPolicy::class);

        config(['rest.authorizations.enabled' => false]);

        $response = $this->post(
            '/api/models/search',
            [

            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new ModelResource()
        );
    }

    public function test_searching_with_no_authorizations(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, RedPolicy::class);

        $response = $this->post(
            '/api/no-authorization/search',
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new NoAuthorizationResource()
        );
    }

    public function test_mutating_with_no_authorizations(): void
    {
        $modelToCreate = ModelFactory::new()->makeOne();

        Gate::policy(Model::class, RedPolicy::class);

        $response = $this->post(
            '/api/no-authorization/mutate',
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

        $this->assertMutatedResponse(
            $response,
            [$modelToCreate],
        );
    }

    public function test_deleting_with_no_authorizations(): void
    {
        $model = ModelFactory::new()->count(1)->createOne();

        Gate::policy(Model::class, RedPolicy::class);

        $response = $this->delete(
            '/api/no-authorization',
            [
                'resources' => [$model->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourceModel($response, [$model], new ModelResource());
        $this->assertDatabaseMissing('models', $model->only('id'));
    }
}
