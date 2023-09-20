<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\CreatePolicy;
use Lomkit\Rest\Tests\Support\Policies\DeletePolicy;
use Lomkit\Rest\Tests\Support\Policies\ForceDeletePolicy;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\RestorePolicy;
use Lomkit\Rest\Tests\Support\Policies\UpdatePolicy;
use Lomkit\Rest\Tests\Support\Policies\ViewPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\AutomaticGatingResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class AutomaticGatingTest extends TestCase
{
    public function test_searching_automatic_gated_resource(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['create', 'view', 'update', 'delete', 'forceDelete', 'restore']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_view'         => true,
                        'authorized_to_update'       => true,
                        'authorized_to_delete'       => true,
                        'authorized_to_restore'      => true,
                        'authorized_to_force_delete' => true,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => ['gates' => ['authorized_to_create' => true]]]
        );
    }

    public function test_searching_automatic_gated_resource_with_global_config_disabled(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);

        config(['rest.automatic_gates.enabled' => false]);

        $response = $this->post(
            '/api/automatic-gating/search',
            [

            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource()
        );
    }

    public function test_searching_automatic_gated_resource_with_create_policy(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, CreatePolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['create', 'view']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_view'         => false,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => ['gates' => ['authorized_to_create' => true]]]
        );
    }

    public function test_searching_automatic_gated_resource_with_view_policy(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, ViewPolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['view', 'create']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_view'         => true,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => ['gates' => ['authorized_to_create' => false]]]
        );
    }

    public function test_searching_automatic_gated_resource_with_update_policy(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, UpdatePolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['update']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_update'       => true,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => []]
        );
    }

    public function test_searching_automatic_gated_resource_with_delete_policy(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, DeletePolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['create', 'delete']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_delete'       => true,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => ['gates' => ['authorized_to_create' => false]]]
        );
    }

    public function test_searching_automatic_gated_resource_with_restore_policy(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, RestorePolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['restore', 'view']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_view'         => false,
                        'authorized_to_restore'      => true,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => []]
        );
    }

    public function test_searching_automatic_gated_resource_with_force_delete_policy(): void
    {
        $model = ModelFactory::new()
            ->create();

        Gate::policy(Model::class, ForceDeletePolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'gates' => ['forceDelete', 'create']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$model],
            new AutomaticGatingResource(),
            [
                [
                    'gates' => [
                        'authorized_to_force_delete' => true,
                    ],
                ],
            ]
        );
        $response->assertJson(
            ['meta' => ['gates' => ['authorized_to_create' => false]]]
        );
    }

    public function test_searching_automatic_gated_resource_with_belongs_to_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(BelongsToManyRelationFactory::new()->count(2))
            ->create()->fresh();
        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'includes' => [
                    [
                        'relation' => 'belongsToManyRelation',
                        'gates' => ['view']
                    ],
                ],
                'sorts' => [
                    ['field' => 'id', 'direction' => 'asc'],
                ],
                'gates' => [
                    'view', 'update', 'create', 'delete', 'restore', 'forceDelete'
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'gates' => [
                        'authorized_to_view'         => true,
                        'authorized_to_update'       => true,
                        'authorized_to_delete'       => true,
                        'authorized_to_restore'      => true,
                        'authorized_to_force_delete' => true,
                    ],
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new BelongsToManyResource())->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource())->relation('belongsToManyRelation')->getPivotFields()
                                        );

                                    return $relation;
                                });
                        })
                        ->toArray(),
                ],
                [
                    'gates' => [
                        'authorized_to_view'         => true,
                        'authorized_to_update'       => true,
                        'authorized_to_delete'       => true,
                        'authorized_to_restore'      => true,
                        'authorized_to_force_delete' => true,
                    ],
                    'belongs_to_many_relation' => [],
                ],
            ]
        );
        $this->assertArrayNotHasKey(
            'gates', $response->json('data.0.belongs_to_many_relation.0')
        );
        $response->assertJson(
            ['meta' => ['gates' => ['authorized_to_create' => true]]]
        );
    }

    public function test_searching_automatic_gated_resource_with_not_requested_includes_gates(): void
    {
        ModelFactory::new()
            ->count(10)
            ->has(
                HasManyRelationFactory::new()
                    ->count(10)
            )
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/automatic-gating/search',
            [
                'includes' => [
                    [
                        'relation' => 'hasManyRelation'
                    ]
                ],
                'gates' => ['view', 'create', 'update', 'delete', 'restore', 'forceDelete']
            ],
            ['Accept' => 'application/json']
        );

        $this->assertArrayNotHasKey(
            'gates', $response->json('data.0.has_many_relation.0')
        );
    }
}
