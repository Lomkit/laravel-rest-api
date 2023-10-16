<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphedByManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchFilteringOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_filtered_by_not_authorized_field(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'non_authorized_field', 'value' => 'value'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.filters.0.field']]);
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_default_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
        ModelFactory::new()->create(['name' => 'not match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
               'search' => [
                   'filters' => [
                       ['field' => 'name', 'value' => 'match'],
                   ],
               ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_in_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['name' => 'match2'])->fresh();
        ModelFactory::new()->create(['name' => 'not match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'in', 'value' => ['match', 'match2']],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_in_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['name' => 'match2'])->fresh();
        ModelFactory::new()->create(['name' => 'not_match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'not in', 'value' => ['not_match']],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_equal_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
        ModelFactory::new()->create(['name' => 'not_match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => '!=', 'value' => 'not_match'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_greater_than_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 2])->fresh();
        ModelFactory::new()->create(['number' => 1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'operator' => '>', 'value' => 1],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_greater_than_or_equal_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 2])->fresh();
        ModelFactory::new()->create(['number' => 1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'operator' => '>=', 'value' => 2],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_less_than_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => 2])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'operator' => '<', 'value' => 2],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_less_than_or_equal_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => 2])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'operator' => '<=', 'value' => 1],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_like_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'like a day'])->fresh();
        ModelFactory::new()->create(['name' => 'not'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'like', 'value' => '%like%'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_like_right_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'like a day'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['name' => 'like'])->fresh();
        ModelFactory::new()->create(['name' => 'not like'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'like', 'value' => 'like%'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_like_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'not'])->fresh();
        ModelFactory::new()->create(['name' => 'its a like today'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'not like', 'value' => '%like%'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_like_right_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'its a like'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['name' => ' like '])->fresh();
        ModelFactory::new()->create(['name' => 'like it'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'not like', 'value' => 'like%'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_or_type(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 3])->fresh();
        ModelFactory::new()->create(['number' => 2])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'value' => 1],
                        ['field' => 'number', 'value' => 3, 'type' => 'or'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_and_type(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();
        ModelFactory::new()->create(['number' => 2, 'name' => 'match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'value' => 1],
                        ['field' => 'name', 'value' => 'match', 'type' => 'and'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_nested_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 2, 'name' => 'match2'])->fresh();
        ModelFactory::new()->create(['number' => 3, 'name' => 'match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        [
                            'nested' => [
                                ['field' => 'number', 'value' => 1],
                                ['field' => 'name', 'value' => 'match', 'type' => 'and'],
                            ],
                        ],
                        ['field' => 'number', 'value' => 2, 'type' => 'or'],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_nested_operator_using_or_type(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 2, 'name' => 'match2'])->fresh();
        ModelFactory::new()->create(['number' => 3, 'name' => 'match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'value' => 2],
                        [
                            'nested' => [
                                ['field' => 'number', 'value' => 1],
                                ['field' => 'name', 'value' => 'match', 'type' => 'and'],
                            ],
                            'type' => 'or',
                        ],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_belongs_to_many_pivot_operation(): void
    {
        $belongsToManyRelation = BelongsToManyRelationFactory::new()
            ->create();

        $matchingModel = ModelFactory::new()
            ->hasAttached($belongsToManyRelation, ['number' => 10], 'belongsToManyRelation')
            ->create(['number' => 1, 'name' => 'match'])->fresh();
        ModelFactory::new()
            ->hasAttached($belongsToManyRelation, ['number' => 11], 'belongsToManyRelation')
            ->create(['number' => 1, 'name' => 'match2'])->fresh();
        ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'value' => 1],
                        ['field' => 'belongsToManyRelation.pivot.number', 'value' => 10],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_morph_to_many_pivot_operation(): void
    {
        $morphToManyRelation = MorphToManyRelationFactory::new()
            ->create();

        $matchingModel = ModelFactory::new()
            ->hasAttached($morphToManyRelation, ['number' => 10], 'morphToManyRelation')
            ->create(['number' => 1, 'name' => 'match'])->fresh();
        ModelFactory::new()
            ->hasAttached($morphToManyRelation, ['number' => 11], 'morphToManyRelation')
            ->create(['number' => 1, 'name' => 'match2'])->fresh();
        ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'value' => 1],
                        ['field' => 'morphToManyRelation.pivot.number', 'value' => 10],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_morphed_by_many_pivot_operation(): void
    {
        $morphedByManyRelation = MorphedByManyRelationFactory::new()
            ->create();

        $matchingModel = ModelFactory::new()
            ->hasAttached($morphedByManyRelation, ['number' => 10], 'morphedByManyRelation')
            ->create(['number' => 1, 'name' => 'match'])->fresh();
        ModelFactory::new()
            ->hasAttached($morphedByManyRelation, ['number' => 11], 'morphedByManyRelation')
            ->create(['number' => 1, 'name' => 'match2'])->fresh();
        ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'value' => 1],
                        ['field' => 'morphedByManyRelation.pivot.number', 'value' => 10],
                    ],
                ]
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }
}
