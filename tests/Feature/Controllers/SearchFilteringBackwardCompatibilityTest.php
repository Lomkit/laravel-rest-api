<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchFilteringBackwardCompatibilityTest extends TestCase
{
    /**
     * The statements captured for the request under test.
     *
     * @var array
     */
    protected array $statements = [];

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Model::class, GreenPolicy::class);

        DB::listen(function ($query) {
            $this->statements[] = $query->sql;
        });
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_field(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel->getKey()]);

        $otherModel = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['number' => 6, 'model_id' => $otherModel->getKey()]);

        ModelFactory::new()->create();

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'hasManyRelation.number', 'value' => 5],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_field_using_not_equal_operator_matches_any_related_row(): void
    {
        // Holds a related row that is not 5, so it matches even though it also holds a 5
        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel->getKey()]);
        HasManyRelationFactory::new()->create(['number' => 6, 'model_id' => $matchingModel->getKey()]);

        $onlyFiveModel = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $onlyFiveModel->getKey()]);

        // Holds no related row at all, so nothing can match
        ModelFactory::new()->create();

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'hasManyRelation.number', 'operator' => '!=', 'value' => 5],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_equal_operator_with_null_value(): void
    {
        $matchingModel = ModelFactory::new()->create(['string' => 'filled'])->fresh();
        ModelFactory::new()->create(['string' => null]);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'string', 'operator' => '!=', 'value' => null],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_nested_when_nesting_is_disabled(): void
    {
        config(['rest.search.max_nesting_depth' => 0]);

        ModelFactory::new()->count(2)->create();

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        [
                            'nested' => [
                                ['field' => 'number', 'value' => 1],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.nested']]);
    }

    public function test_getting_a_list_of_resources_filtered_by_not_allowed_operator_alongside_includes(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'operator' => 'regexp', 'value' => '%a%'],
                    ],
                    'includes' => [
                        ['relation' => 'hasManyRelation'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.operator']]);
    }

    public function test_the_statement_built_for_model_field_filters(): void
    {
        $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'number', 'operator' => '>', 'value' => 1],
                        ['field' => 'name', 'operator' => 'like', 'value' => '%a%', 'type' => 'or'],
                        ['field' => 'id', 'operator' => 'in', 'value' => [1, 2]],
                        ['field' => 'id', 'operator' => 'not in', 'value' => [3]],
                        [
                            'nested' => [
                                ['field' => 'string', 'value' => null],
                                ['field' => 'unique', 'operator' => '!=', 'value' => 1, 'type' => 'or'],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $this->assertStringContainsString(
            '(models.number > ? or models.name like ? and models.id in (?, ?) and models.id not in (?)'
            .' and (models.string is null or models.unique != ?))',
            $this->searchStatement()
        );
    }

    public function test_the_statement_built_for_relation_field_filters(): void
    {
        $this->post(
            '/api/models/search',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'hasManyRelation.number', 'operator' => '!=', 'value' => 5],
                        ['field' => 'hasManyRelation.id', 'operator' => 'not in', 'value' => [1, 2], 'type' => 'or'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $this->assertStringContainsString(
            '(exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.number != ?)'
            .' or exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.id not in (?, ?)))',
            $this->searchStatement()
        );
    }

    /**
     * Find the select that carried the search filters, with the identifier quoting of the
     * current driver removed so the assertion holds on MySQL, PostgreSQL and SQLite alike.
     *
     * @return string
     */
    protected function searchStatement(): string
    {
        foreach ($this->statements as $statement) {
            $statement = str_replace(['`', '"', '[', ']', '::text'], '', $statement);

            if (str_starts_with($statement, 'select') && str_contains($statement, 'from models where')) {
                return $statement;
            }
        }

        $this->fail('No select carrying the search filters was captured, out of: '.implode(' | ', $this->statements));
    }
}
