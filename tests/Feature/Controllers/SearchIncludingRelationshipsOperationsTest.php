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
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyThroughRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneOfManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasOneRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelWithFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToRelationFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyThroughRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneOfManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\ModelWith;
use Lomkit\Rest\Tests\Support\Models\MorphToRelation;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\BelongsToResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\HasManyThroughResource;
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
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.relation']]);
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
                            'relation' => 'hasManyRelation',
                            'filters' => [
                                ['field' => 'unauthorized_field', 'value' => 10000],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.filters.0.field']]);
    }

    public function test_getting_a_list_of_resources_including_relation_with_unauthorized_filters_on_multiple_includes(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'hasManyRelation.belongsToRelation',
                        ],
                        [
                            'relation' => 'hasManyRelation',
                            'filters' => [
                                ['field' => 'unauthorized_field', 'value' => 10000],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.1.filters.0.field', 'search.includes.0.relation']]);
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
                            'relation' => 'hasManyRelation',
                            'filters' => [
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
            new ModelResource,
            [
                [
                    'has_many_relation' => $matchingModel->hasManyRelation()
                        ->where('number', 10000)
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            return $relation->only(
                                (new HasManyResource)->getFields(app()->make(RestRequest::class))
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
            new ModelResource,
            [
                [
                    'belongs_to_relation' => $matchingModel->belongsToRelation->only((new BelongsToResource)->getFields(app()->make(RestRequest::class))),
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
            new ModelResource,
            [
                [
                    'belongs_to_relation' => $matchingModel->belongsToRelation->only((new BelongsToResource)->getFields(app()->make(RestRequest::class))),
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
            new ModelResource,
            [
                [
                    'belongs_to_relation' => array_merge(
                        $matchingModelBelongsToRelation
                            ->only((new BelongsToResource)->getFields(app()->make(RestRequest::class))),
                        [
                            'models' => $matchingModelBelongsToRelation->models
                                ->map(function ($model) {
                                    return $model->only((new ModelResource)->getFields(app()->make(RestRequest::class)));
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

    public function test_getting_a_list_of_resources_including_belongs_to_has_many_relation_using_nested_include(): void
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
                        [
                            'relation' => 'belongsToRelation',
                            'includes' => [
                                ['relation' => 'models'],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $matchingModelBelongsToRelation = $matchingModel->belongsToRelation;

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'belongs_to_relation' => array_merge(
                        $matchingModelBelongsToRelation
                            ->only((new BelongsToResource)->getFields(app()->make(RestRequest::class))),
                        [
                            'models' => $matchingModelBelongsToRelation->models()
                                ->orderBy('id')
                                ->get()
                                ->map(function ($model) {
                                    return $model->only((new ModelResource)->getFields(app()->make(RestRequest::class)));
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

    public function test_including_unauthorized_nested_relation_returns_validation_error(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToRelation',
                            'includes' => [
                                ['relation' => 'unauthorized'],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.includes.0.relation']]);
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
            new ModelResource,
            [
                [
                    'belongs_to_many_relation' => [],
                ],
                [
                    'belongs_to_many_relation' => $matchingModelBelongsToManyQueryChangesRelations
                        ->map(function (BelongsToManyRelation $belongsToManyRelation) {
                            return array_merge(
                                $belongsToManyRelation
                                    ->only((new BelongsToManyResource)->getFields(app()->make(RestRequest::class))),
                                [
                                    'model' => null,
                                    'belongs_to_many_pivot' => Arr::only(
                                        $belongsToManyRelation
                                            ->belongs_to_many_pivot
                                            ->toArray(),
                                        Arr::first(
                                            (new ModelResource)->getRelations(app()->make(RestRequest::class)),
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
            new ModelResource,
            [
                [
                    'has_one_relation' => $matchingModel->hasOneRelation->only(
                        (new HasOneResource)->getFields(app()->make(RestRequest::class))
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
            new ModelResource,
            [
                [
                    'has_one_of_many_relation' => $matchingModel->hasOneOfManyRelation->only(
                        (new HasOneOfManyResource)->getFields(app()->make(RestRequest::class))
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
                            'sorts' => [
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
            new ModelResource,
            [
                [
                    'has_many_relation' => $matchingModel->hasManyRelation()
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            return $relation->only(
                                (new HasManyResource)->getFields(app()->make(RestRequest::class))
                            );
                        })->toArray(),
                ],
                [
                    'has_many_relation' => [],
                ],
            ]
        );
    }

    public function test_getting_a_list_of_resources_including_has_many_relation_with_eager_loading_relations(): void
    {
        $matchingModel = ModelFactory::new()
            ->createOne()->fresh();

        $hasMany = HasManyRelationFactory::new()
            ->for($matchingModel)
            ->createOne();

        HasManyThroughRelationFactory::new()
            ->for($hasMany)
            ->createOne();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);
        Gate::policy(HasManyThroughRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'hasManyRelationWithEagerLoadingRelation',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource,
            [
                [
                    'has_many_relation_with_eager_loading_relation' => $matchingModel->hasManyRelationWithEagerLoadingRelation()
                        ->orderBy('id')
                        ->get()
                        ->map(function ($relation) {
                            $relation->has_many_through_relation = $relation->hasManyThroughRelation
                                ->map(function ($relation) {
                                    $relation->has_many_relation = $relation->hasManyRelation->only(
                                        (new HasManyResource)->getFields(app()->make(RestRequest::class))
                                    );

                                    return $relation->only(array_merge(
                                        (new HasManyThroughResource)->getFields(app()->make(RestRequest::class)),
                                        ['has_many_relation']
                                    ));
                                })->toArray();

                            return $relation->only(array_merge(
                                (new HasManyResource)->getFields(app()->make(RestRequest::class)),
                                ['has_many_through_relation']
                            ));
                        })->toArray(),
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
            new ModelResource,
            [
                [
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new BelongsToManyResource)->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
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
                            'limit' => 1,
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->limit(1)
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new BelongsToManyResource)->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
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
                            'filters' => [
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
            new ModelResource,
            [
                [
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new BelongsToManyResource)->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
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
                            'filters' => [
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
            new ModelResource,
            [
                [
                    'belongs_to_many_relation' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new BelongsToManyResource)->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
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

    public function test_including_same_relation_twice_with_different_aliases_and_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                HasManyRelationFactory::new()
                    ->state(['number' => 10000])
            )
            ->has(
                HasManyRelationFactory::new()
                    ->state(['number' => 20000])
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
                            'relation' => 'hasManyRelation',
                            'alias' => 'highNumber',
                            'filters' => [
                                ['field' => 'number', 'value' => 10000],
                            ],
                        ],
                        [
                            'relation' => 'hasManyRelation',
                            'alias' => 'lowNumber',
                            'filters' => [
                                ['field' => 'number', 'value' => 20000],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $relationWithNumber = function ($number) use ($matchingModel) {
            return $matchingModel->hasManyRelation()
                ->where('number', $number)
                ->orderBy('id')
                ->get()
                ->map(function ($relation) {
                    return $relation->only(
                        (new HasManyResource)->getFields(app()->make(RestRequest::class))
                    );
                })->toArray();
        };

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'highNumber' => $relationWithNumber(10000),
                    'lowNumber' => $relationWithNumber(20000),
                ],
                [
                    'highNumber' => [],
                    'lowNumber' => [],
                ],
            ]
        );
    }

    public function test_including_relation_with_alias_uses_verbatim_key(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(HasManyRelationFactory::new())
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'hasManyRelation',
                            'alias' => 'myCamelAlias',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $this->assertArrayHasKey('myCamelAlias', $response->json('data.0'));
        $this->assertArrayNotHasKey('my_camel_alias', $response->json('data.0'));
        $this->assertArrayNotHasKey('has_many_relation', $response->json('data.0'));
        $this->assertCount(1, $response->json('data.0.myCamelAlias'));
    }

    public function test_including_nested_relation_with_alias(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($belongsTo)
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToRelation',
                            'includes' => [
                                ['relation' => 'models', 'alias' => 'linkedModels'],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $this->assertArrayHasKey('belongs_to_relation', $response->json('data.0'));
        $this->assertArrayHasKey('linkedModels', $response->json('data.0.belongs_to_relation'));
        $this->assertArrayNotHasKey('models', $response->json('data.0.belongs_to_relation'));
    }

    public function test_including_belongs_to_relation_with_alias(): void
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
                        ['relation' => 'belongsToRelation', 'alias' => 'owner'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'owner' => $matchingModel->belongsToRelation->only(
                        (new BelongsToResource)->getFields(app()->make(RestRequest::class))
                    ),
                ],
                [
                    'owner' => null,
                ],
            ]
        );
    }

    public function test_including_belongs_to_many_relation_with_alias_keeps_pivot(): void
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
                        ['relation' => 'belongsToManyRelation', 'alias' => 'tags'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource,
            [
                [
                    'tags' => $matchingModel->belongsToManyRelation()
                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($relation) use ($pivotAccessor) {
                            return collect($relation->only(
                                array_merge((new BelongsToManyResource)->getFields(app()->make(RestRequest::class)), [$pivotAccessor])
                            ))
                                ->pipe(function ($relation) use ($pivotAccessor) {
                                    $relation[$pivotAccessor] = collect($relation[$pivotAccessor]->toArray())
                                        ->only(
                                            (new ModelResource)->relation('belongsToManyRelation')->getPivotFields()
                                        );

                                    return $relation;
                                });
                        })
                        ->toArray(),
                ],
                [
                    'tags' => [],
                ],
            ]
        );
    }

    public function test_including_nested_relation_with_a_dotted_path_applies_selects_to_the_deepest_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()->for($belongsTo)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToRelation.models',
                            'selects' => [['field' => 'id']],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        // The intermediate relation keeps the fields of its resource, the deepest one is narrowed.
        $this->assertSame(
            (new BelongsToResource)->getFields(app()->make(RestRequest::class)),
            array_keys(Arr::except($response->json('data.0.belongs_to_relation'), ['models']))
        );
        $this->assertSame(
            [['id' => $matchingModel->id]],
            $response->json('data.0.belongs_to_relation.models')
        );
    }

    public function test_including_nested_relation_with_a_deep_dotted_path_applies_selects_to_the_deepest_relation(): void
    {
        $belongsTo = BelongsToRelationFactory::new()->create();
        $matchingModel = ModelFactory::new()
            ->for($belongsTo)
            ->has(HasManyRelationFactory::new())
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        [
                            'relation' => 'belongsToRelation.models.hasManyRelation',
                            'selects' => [['field' => 'id']],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        // Only the deepest relation of the path is narrowed, every level above keeps its fields.
        $this->assertSame(
            (new ModelResource)->getFields(app()->make(RestRequest::class)),
            array_keys(Arr::except($response->json('data.0.belongs_to_relation.models.0'), ['has_many_relation']))
        );
        $this->assertSame(
            [['id' => $matchingModel->hasManyRelation->first()->id]],
            $response->json('data.0.belongs_to_relation.models.0.has_many_relation')
        );
    }

    public function test_including_relation_with_alias_hydrates_every_parent(): void
    {
        ModelFactory::new()
            ->count(3)
            ->has(HasManyRelationFactory::new()->count(2))
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasManyRelation', 'alias' => 'kids'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $this->assertSame(
            [2, 2, 2],
            array_map(fn ($model) => count($model['kids']), $response->json('data'))
        );
    }

    public function test_including_belongs_to_relation_with_alias_hydrates_every_parent(): void
    {
        // Each model gets its own distinct parent, so a leaking constraint cannot go unnoticed.
        $expectedOwnerIds = collect(range(1, 3))
            ->map(function () {
                return ModelFactory::new()
                    ->for(BelongsToRelationFactory::new()->create())
                    ->create()
                    ->belongsToRelation
                    ->id;
            })
            ->all();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'belongsToRelation', 'alias' => 'owner'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $this->assertSame(
            $expectedOwnerIds,
            array_map(fn ($model) => $model['owner']['id'], $response->json('data'))
        );
    }

    public function test_including_morph_to_relation_with_alias(): void
    {
        $morphTo = MorphToRelationFactory::new()->create();
        ModelFactory::new()
            ->count(2)
            ->state([
                'morph_to_relation_type' => MorphToRelation::class,
                'morph_to_relation_id' => $morphTo->id,
            ])
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(MorphToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'morphToRelation', 'alias' => 'morphed'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        foreach ($response->json('data') as $model) {
            $this->assertSame($morphTo->id, $model['morphed']['id']);
            $this->assertArrayNotHasKey('morph_to_relation', $model);
        }
    }

    public function test_including_relation_both_natively_and_aliased(): void
    {
        ModelFactory::new()
            ->count(2)
            ->has(HasManyRelationFactory::new()->count(2))
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasManyRelation'],
                        ['relation' => 'hasManyRelation', 'alias' => 'kids'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        foreach ($response->json('data') as $model) {
            $this->assertCount(2, $model['has_many_relation']);
            $this->assertCount(2, $model['kids']);
        }
    }

    public function test_including_relation_with_alias_on_a_nested_relation(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'belongsToRelation.models', 'alias' => 'linkedModels'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.alias']]);
    }

    public function test_including_relation_with_alias_conflicting_with_a_field(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasManyRelation', 'alias' => 'name'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.alias']]);
    }

    public function test_including_relation_with_alias_conflicting_with_the_gates_key(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'gates' => ['view'],
                    'includes' => [
                        ['relation' => 'hasManyRelation', 'alias' => config('rest.gates.key')],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.alias']]);
    }

    public function test_including_relation_with_alias_conflicting_with_a_relation(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);
        Gate::policy(BelongsToRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'belongsToRelation'],
                        ['relation' => 'hasManyRelation', 'alias' => 'belongsToRelation'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.1.alias']]);
    }

    public function test_including_relation_with_a_non_identifier_alias(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasManyRelation', 'alias' => '0'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.includes.0.alias']]);
    }

    public function test_including_the_same_relation_twice_under_the_same_alias(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasManyRelation', 'alias' => 'kids'],
                        ['relation' => 'hasManyRelation', 'alias' => 'kids'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(
            ['message', 'errors' => ['search.includes.0.alias', 'search.includes.1.alias']]
        );
    }
}
