<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchAggregatesOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_aggregating_by_unauthorized_relation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'unauthorized_relation',
                            'type'     => 'min',
                            'field'    => 'id',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.aggregates.0.relation']]);
    }

    public function test_getting_a_list_of_resources_aggregating_by_unauthorized_type(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'unauthorized_type',
                            'field'    => 'id',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.aggregates.0.type']]);
    }

    public function test_getting_a_list_of_resources_aggregating_by_unauthorized_field(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                            'field'    => 'unauthorized_field',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.aggregates.0']]);
    }

    public function test_getting_a_list_of_resources_aggregating_by_not_specifying_field_when_necessary(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.aggregates.0.field']]);
    }

    public function test_getting_a_list_of_resources_aggregating_by_specifying_field_when_not_necessary(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'exists',
                            'field'    => 'id',
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.aggregates.0.field']]);
    }

    public function test_getting_a_list_of_resources_aggregating_by_min_number(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                            'field'    => 'number',
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
                ['belongs_to_many_relation_min_number' => $matchingModel->belongsToManyRelation()->orderBy('number', 'asc')->first()->number],
                ['belongs_to_many_relation_min_number' => $matchingModel2->belongsToManyRelation()->orderBy('number', 'asc')->first()->number],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_min_number_with_alias(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                            'field'    => 'number',
                            'alias'    => 'min_alias',
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
                ['min_alias' => $matchingModel->belongsToManyRelation()->orderBy('number', 'asc')->first()->number],
                ['min_alias' => $matchingModel2->belongsToManyRelation()->orderBy('number', 'asc')->first()->number],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_max_number(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'max',
                            'field'    => 'number',
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
                ['belongs_to_many_relation_max_number' => $matchingModel->belongsToManyRelation()->orderBy('number', 'desc')->first()->number],
                ['belongs_to_many_relation_max_number' => $matchingModel2->belongsToManyRelation()->orderBy('number', 'desc')->first()->number],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_max_number_with_alias(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'max',
                            'field'    => 'number',
                            'alias'    => 'max_alias',
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
                ['max_alias' => $matchingModel->belongsToManyRelation()->orderBy('number', 'desc')->first()->number],
                ['max_alias' => $matchingModel2->belongsToManyRelation()->orderBy('number', 'desc')->first()->number],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_avg_number(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'avg',
                            'field'    => 'number',
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
                ['belongs_to_many_relation_avg_number' => $matchingModel->belongsToManyRelation()->avg('belongs_to_many_relations.number')],
                ['belongs_to_many_relation_avg_number' => $matchingModel2->belongsToManyRelation()->avg('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_avg_number_with_alias(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'avg',
                            'field'    => 'number',
                            'alias'    => 'avg_alias',
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
                ['avg_alias' => $matchingModel->belongsToManyRelation()->avg('belongs_to_many_relations.number')],
                ['avg_alias' => $matchingModel2->belongsToManyRelation()->avg('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_sum_number(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'sum',
                            'field'    => 'number',
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
                ['belongs_to_many_relation_sum_number' => $matchingModel->belongsToManyRelation()->sum('belongs_to_many_relations.number')],
                ['belongs_to_many_relation_sum_number' => $matchingModel2->belongsToManyRelation()->sum('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_sum_number_with_alias(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'sum',
                            'field'    => 'number',
                            'alias'    => 'sum_alias',
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
                ['sum_alias' => $matchingModel->belongsToManyRelation()->sum('belongs_to_many_relations.number')],
                ['sum_alias' => $matchingModel2->belongsToManyRelation()->sum('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_count_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'count',
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
                ['belongs_to_many_relation_count' => $matchingModel->belongsToManyRelation()->count()],
                ['belongs_to_many_relation_count' => $matchingModel2->belongsToManyRelation()->count()],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_count_relation_with_alias(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'count',
                            'alias'     => 'count_alias',
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
                ['count_alias' => $matchingModel->belongsToManyRelation()->count()],
                ['count_alias' => $matchingModel2->belongsToManyRelation()->count()],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_exists_relation(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'exists',
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
                ['belongs_to_many_relation_exists' => false],
                ['belongs_to_many_relation_exists' => true],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_exists_relation_with_alias(): void
    {
        $matchingModel = ModelFactory::new()
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(belongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'exists',
                            'alias'     => 'exists_alias',
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
                ['exists_alias' => false],
                ['exists_alias' => true],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_min_number_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'min',
                            'field'    => 'number',
                            'filters'  => [
                                ['field' => 'number', 'operator' => '>', 'value' => 200],
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
                ['belongs_to_many_relation_min_number' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'asc')->where('belongs_to_many_relations.number', '>', 200)->first()->number],
                ['belongs_to_many_relation_min_number' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'asc')->where('belongs_to_many_relations.number', '>', 200)->first()->number],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_max_number_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'max',
                            'field'    => 'number',
                            'filters'  => [
                                ['field' => 'number', 'operator' => '<', 'value' => 200],
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
                ['belongs_to_many_relation_max_number' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->first()->number],
                ['belongs_to_many_relation_max_number' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->first()->number],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_avg_number_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'avg',
                            'field'    => 'number',
                            'filters'  => [
                                ['field' => 'number', 'operator' => '<', 'value' => 200],
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
                ['belongs_to_many_relation_avg_number' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->avg('belongs_to_many_relations.number')],
                ['belongs_to_many_relation_avg_number' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->avg('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_sum_number_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'sum',
                            'field'    => 'number',
                            'filters'  => [
                                ['field' => 'number', 'operator' => '<', 'value' => 200],
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
                ['belongs_to_many_relation_sum_number' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->sum('belongs_to_many_relations.number')],
                ['belongs_to_many_relation_sum_number' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->sum('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_sum_number_with_other_number_filter(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'sum',
                            'field'    => 'number',
                            'filters'  => [
                                ['field' => 'other_number', 'operator' => '<', 'value' => 200],
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
                ['belongs_to_many_relation_sum_number' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.other_number', '<', 200)->sum('belongs_to_many_relations.number')],
                ['belongs_to_many_relation_sum_number' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.other_number', '<', 200)->sum('belongs_to_many_relations.number')],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_count_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'count',
                            'filters'  => [
                                ['field' => 'number', 'operator' => '<', 'value' => 200],
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
                ['belongs_to_many_relation_count' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->count()],
                ['belongs_to_many_relation_count' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->count()],
            ]
        );
    }

    public function test_getting_a_list_of_resources_aggregating_by_exists_with_filters(): void
    {
        $matchingModel = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()
            ->has(
                BelongsToManyRelationFactory::new()
                    ->count(20)
            )
            ->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(BelongsToManyRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'aggregates' => [
                        [
                            'relation' => 'belongsToManyRelation',
                            'type'     => 'exists',
                            'filters'  => [
                                ['field' => 'number', 'operator' => '<', 'value' => 200],
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
                ['belongs_to_many_relation_exists' => $matchingModel->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->exists()],
                ['belongs_to_many_relation_exists' => $matchingModel2->belongsToManyRelation()->orderBy('belongs_to_many_relations.number', 'desc')->where('belongs_to_many_relations.number', '<', 200)->exists()],
            ]
        );
    }
}
