<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelWithFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\ModelWith;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneOfManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasOneResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchIncludingRelationshipsOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_including_unauthorized_relation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'unauthorized'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.includes.0.relation']]);
    }

    public function test_getting_a_list_of_resources_including_relation_with_unauthorized_filters(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation'   => 'hasManyRelation',
                            'filters'    => [
                                ['field' => 'unauthorized_field', 'value' => 10000],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.includes.0.filters.0.field']]);
    }

    public function test_getting_a_list_of_resources_including_relation_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                HasManyRelationFactory::new()
                    ->state(['number' => 10000])
            )
            ->has(
                HasManyRelationFactory::new()
            )
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation'   => 'hasManyRelation',
                            'filters'    => [
                                ['field' => 'number', 'value' => 10000],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_many_relation' => $matchingModel->hasManyRelation()
                        ->where('number', 10000)
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            return $relation->only(
                                (new HasManyResource())->getFields(app()->make(RestRequest::class))
                            );
                        })->toArray(),
                ],
                [
                    'has_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($belongsTo)
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'belongsToRelation'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'belongs_to_relation' => $matchingModel->belongsToRelation->only((new BelongsToResource())->getFields(app()->make(RestRequest::class))),
                ],
                [
                    'belongs_to_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_with_auto_loaded_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelWithFactory::new()
            ->for($belongsTo)
            ->create()->fresh();

        $matchingModel2 = ModelWithFactory::new()->create()->fresh();

        Gate::policy(ModelWith::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/model-withs/search',
            [
                'search' => [],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'belongs_to_relation' => $matchingModel->belongsToRelation->only((new BelongsToResource())->getFields(app()->make(RestRequest::class))),
                ],
                [
                    'belongs_to_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_has_many_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($belongsTo)
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'belongsToRelation.models'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $matchingModelBelongsToRelation = $matchingModel->belongsToRelation;

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'belongs_to_relation' => array_merge(
                        $matchingModelBelongsToRelation
                            ->only((new BelongsToResource())->getFields(app()->make(RestRequest::class))),
                        [
                            'models' => $matchingModelBelongsToRelation->models
                                ->map(function ($model) {
                                    return $model->only((new ModelResource())->getFields(app()->make(RestRequest::class)));
                                })
                                ->toArray(),
                        ]
                    ),
                ],
                [
                    'belongs_to_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_distant_relation_with_intermediary_search_query_condition(): void
    {
        $matchingModel = ModelFactory::new()->create(
            ['number' => 1]
        )->fresh();

        $belongsToMany = BelongsToManyRelationFactory::new()
            ->for($matchingModel)
            ->create();

        $matchingModel2 = ModelFactory::new()
            ->afterCreating(function (Model $model) use ($belongsToMany) {
                $model->belongsToManyQueryChangesRelation()
                    ->attach($belongsToMany);
            })
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'belongsToManyRelation.model'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $matchingModelBelongsToManyQueryChangesRelations = $matchingModel2->belongsToManyQueryChangesRelation;

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'belongs_to_many_relation' => [],
                ],
                [
                    'belongs_to_many_relation' => $matchingModelBelongsToManyQueryChangesRelations
                            ->map(function (BelongsToManyRelation $belongsToManyRelation) {
                                return array_merge(
                                    $belongsToManyRelation
                                        ->only((new BelongsToManyResource())->getFields(app()->make(RestRequest::class))),
                                    [
                                        'model'                 => null,
                                        'belongs_to_many_pivot' => Arr::only(
                                            $belongsToManyRelation
                                                ->belongs_to_many_pivot
                                                ->toArray(),
                                            Arr::first(
                                                (new ModelResource())->getRelations(app()->make(RestRequest::class)),
                                                function (Relation $relation) {
                                                    return $relation->relation === 'belongsToManyRelation';
                                                }
                                            )->getPivotFields()
                                        ),
                                    ]
                                );
                            })
                            ->toArray(),
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_one_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        HasOneRelationFactory::new()
            ->for($matchingModel)
            ->create();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasOneRelation'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_one_relation' => $matchingModel->hasOneRelation->only(
                        (new HasOneResource())->getFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'has_one_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_one_of_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        HasOneOfManyRelationFactory::new()
            ->for($matchingModel)
            ->create();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneOfManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasOneOfManyRelation'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_one_of_many_relation' => $matchingModel->hasOneOfManyRelation->only(
                        (new HasOneOfManyResource())->getFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'has_one_of_many_relation' => null,
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(HasManyRelationFactory::new()->count(2))
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'hasManyRelation',
                            'sorts'    => [
                                ['field' => 'id', 'direction' => 'asc'],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'has_many_relation' => $matchingModel->hasManyRelation()
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            return $relation->only(
                                (new HasManyResource())->getFields(app()->make(RestRequest::class))
                            );
                        })->toArray(),
                ],
                [
                    'has_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_many_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(BelongsToManyRelationFactory::new()->count(2))
            ->create()->fresh();
        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToManyRelation',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
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
                    'belongs_to_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_many_relation_and_limit_results(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(BelongsToManyRelationFactory::new()->count(2))
            ->create()->fresh();
        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'limit'    => 1,
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->limit(1)
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
                    'belongs_to_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_many_relation_and_filtering_on_pivot(): void
    {
        $matchingModel = ModelFactory::new()
            ->hasAttached(
                BelongsToManyRelationFactory::new()->count(1),
                ['number' => 3],
                'belongsToManyRelation'
            )
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()
            ->hasAttached(
                BelongsToManyRelationFactory::new()->count(1),
                ['number' => 1],
                'belongsToManyRelation'
            )
            ->create()->fresh();

        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'filters'  => [
                                ['field' => 'models.pivot.number', 'operator' => '>', 'value' => 2],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
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
                    'belongs_to_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_belongs_to_many_relation_and_filtering_on_pivot_with_null_value(): void
    {
        $matchingModel = ModelFactory::new()
            ->hasAttached(
                BelongsToManyRelationFactory::new()->count(1),
                ['number' => null],
                'belongsToManyRelation'
            )
            ->create()->fresh();

        $matchingModel2 = ModelFactory::new()
            ->hasAttached(
                BelongsToManyRelationFactory::new()->count(1),
                ['number' => 1],
                'belongsToManyRelation'
            )
            ->create()->fresh();

        $pivotAccessor = $matchingModel->belongsToManyRelation()->getPivotAccessor();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'filters'  => [
                                ['field' => 'models.pivot.number', 'operator' => '=', 'value' => null],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource(),
            [
                [
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
                    'belongs_to_many_relation' => [],
                ],
            ]
        );
    }
}
