<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyThroughRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use PHPUnit\Framework\Attributes\DataProvider;

class SearchAdvancedFilteringOperationsTest extends TestCase
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

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_ilike_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'Alpha'])->fresh();
        ModelFactory::new()->create(['name' => 'Beta']);

        $response = $this->search([
            ['field' => 'name', 'operator' => 'ilike', 'value' => '%ALPH%'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_ilike_operator(): void
    {
        ModelFactory::new()->create(['name' => 'Alpha']);
        $matchingModel = ModelFactory::new()->create(['name' => 'Beta'])->fresh();

        $response = $this->search([
            ['field' => 'name', 'operator' => 'not ilike', 'value' => '%ALPH%'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_between_operator(): void
    {
        ModelFactory::new()->create(['number' => 1]);
        $matchingModel = ModelFactory::new()->create(['number' => 5])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 10])->fresh();
        ModelFactory::new()->create(['number' => 11]);

        $response = $this->search([
            ['field' => 'number', 'operator' => 'between', 'value' => [5, 10]],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_not_between_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => 5]);
        ModelFactory::new()->create(['number' => 10]);
        $matchingModel2 = ModelFactory::new()->create(['number' => 11])->fresh();

        $response = $this->search([
            ['field' => 'number', 'operator' => 'not between', 'value' => [5, 10]],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_is_null_operator(): void
    {
        ModelFactory::new()->create(['string' => 'filled']);
        $matchingModel = ModelFactory::new()->create(['string' => null])->fresh();

        $response = $this->search([
            ['field' => 'string', 'operator' => 'is null'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_model_field_using_is_not_null_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['string' => 'filled'])->fresh();
        ModelFactory::new()->create(['string' => null]);

        $response = $this->search([
            ['field' => 'string', 'operator' => 'is not null', 'value' => null],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_null_and_between_operators_using_or_type(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'string' => null])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 50, 'string' => 'filled'])->fresh();
        ModelFactory::new()->create(['number' => 2, 'string' => 'filled']);

        $response = $this->search([
            ['field' => 'string', 'operator' => 'is null'],
            ['field' => 'number', 'operator' => 'between', 'value' => [40, 60], 'type' => 'or'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_field_using_between_operator(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel->getKey()]);

        $otherModel = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['number' => 8, 'model_id' => $otherModel->getKey()]);

        ModelFactory::new()->create();

        $response = $this->search([
            ['field' => 'hasManyRelation.number', 'operator' => 'between', 'value' => [4, 6]],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_pivot_field_using_not_between_operator(): void
    {
        $belongsToManyRelation = BelongsToManyRelationFactory::new()->create();

        ModelFactory::new()
            ->hasAttached($belongsToManyRelation, ['number' => 10], 'belongsToManyRelation')
            ->create();
        $matchingModel = ModelFactory::new()
            ->hasAttached($belongsToManyRelation, ['number' => 11], 'belongsToManyRelation')
            ->create()->fresh();
        ModelFactory::new()->create();

        $response = $this->search([
            ['field' => 'belongsToManyRelation.pivot.number', 'operator' => 'not between', 'value' => [9, 10]],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource()
        );
    }

    #[DataProvider('wrongValueProvider')]
    public function test_getting_a_list_of_resources_filtered_by_operator_with_wrong_value(string $operator, mixed $value): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->search([
            ['field' => 'string', 'operator' => $operator, 'value' => $value],
        ]);

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.value']]);
    }

    public static function wrongValueProvider(): array
    {
        return [
            'between with a scalar'       => ['between', 5],
            'between with one bound'      => ['between', [5]],
            'between with three bounds'   => ['between', [5, 6, 7]],
            'between without value'       => ['between', null],
            'not between with a scalar'   => ['not between', 5],
            'between with empty bounds'   => ['between', [[], []]],
            'between with an array bound' => ['between', [[1], []]],
            'between with nested bounds'  => ['between', [[1, 2, 3], [4]]],
            'not between with arrays'     => ['not between', [[1], [2]]],
            'is null with a value'        => ['is null', 'filled'],
            'is not null with a value'    => ['is not null', 'filled'],
            'ilike with an array'         => ['ilike', ['%a%']],
            'ilike without value'         => ['ilike', null],
            'not ilike with an array'     => ['not ilike', ['%a%']],
        ];
    }

    public function test_the_statement_built_for_ilike_between_and_null_operators(): void
    {
        $this->search([
            ['field' => 'name', 'operator' => 'ilike', 'value' => '%a%'],
            ['field' => 'name', 'operator' => 'not ilike', 'value' => '%b%', 'type' => 'or'],
            ['field' => 'number', 'operator' => 'between', 'value' => [1, 2]],
            ['field' => 'number', 'operator' => 'not between', 'value' => [3, 4], 'type' => 'or'],
            ['field' => 'string', 'operator' => 'is null'],
            ['field' => 'string', 'operator' => 'is not null', 'type' => 'or'],
            ['field' => 'hasManyRelation.number', 'operator' => 'between', 'value' => [5, 6]],
        ])->assertStatus(200);

        // Only PostgreSQL has a dedicated case insensitive operator
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $this->assertStringContainsString(
            '(models.name '.$like.' ? or models.name not '.$like.' ?'
            .' and models.number between ? and ? or models.number not between ? and ?'
            .' and models.string is null or models.string is not null'
            .' and exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.number between ? and ?))',
            $this->searchStatement()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_matching_the_same_related_entry(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel->getKey()]);

        // Each condition matches a different related entry, none matches both
        $splitModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 3, 'model_id' => $splitModel->getKey()]);
        HasManyRelationFactory::new()->create(['number' => 8, 'model_id' => $splitModel->getKey()]);

        ModelFactory::new()->create();

        $response = $this->search([
            ['relation' => 'hasManyRelation', 'nested' => [
                ['field' => 'number', 'operator' => '>=', 'value' => 4],
                ['field' => 'number', 'operator' => '<=', 'value' => 6],
            ]],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_dotted_fields_matching_different_related_entries(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel->getKey()]);

        $splitModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 3, 'model_id' => $splitModel->getKey()]);
        HasManyRelationFactory::new()->create(['number' => 8, 'model_id' => $splitModel->getKey()]);

        ModelFactory::new()->create();

        $response = $this->search([
            ['field' => 'hasManyRelation.number', 'operator' => '>=', 'value' => 4],
            ['field' => 'hasManyRelation.number', 'operator' => '<=', 'value' => 6],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel, $splitModel], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_not_existing(): void
    {
        $model = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $model->getKey()]);
        HasManyRelationFactory::new()->create(['number' => 8, 'model_id' => $model->getKey()]);

        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 8, 'model_id' => $matchingModel->getKey()]);

        $matchingModel2 = ModelFactory::new()->create()->fresh();

        $response = $this->search([
            ['relation' => 'hasManyRelation', 'exists' => false, 'nested' => [
                ['field' => 'number', 'value' => 5],
            ]],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel, $matchingModel2], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_without_nested(): void
    {
        $model = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['model_id' => $model->getKey()]);

        $matchingModel = ModelFactory::new()->create()->fresh();

        $response = $this->search([
            ['relation' => 'hasManyRelation', 'exists' => false],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_on_a_dotted_relation(): void
    {
        $model = ModelFactory::new()->create();
        $hasManyRelation = HasManyRelationFactory::new()->create(['model_id' => $model->getKey()]);
        HasManyThroughRelationFactory::new()->create(['number' => 5, 'has_many_relation_id' => $hasManyRelation->getKey()]);

        $matchingModel = ModelFactory::new()->create()->fresh();
        $hasManyRelation = HasManyRelationFactory::new()->create(['model_id' => $matchingModel->getKey()]);
        HasManyThroughRelationFactory::new()->create(['number' => 8, 'has_many_relation_id' => $hasManyRelation->getKey()]);

        $response = $this->search([
            ['relation' => 'hasManyRelation.hasManyThroughRelation', 'exists' => false, 'nested' => [
                ['field' => 'number', 'value' => 5],
            ]],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_using_or_type(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();

        $matchingModel2 = ModelFactory::new()->create(['number' => 2])->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel2->getKey()]);

        ModelFactory::new()->create(['number' => 2]);

        $response = $this->search([
            ['field' => 'number', 'value' => 1],
            ['relation' => 'hasManyRelation', 'type' => 'or', 'nested' => [
                ['field' => 'number', 'value' => 5],
            ]],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel, $matchingModel2], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_on_a_pivot_field(): void
    {
        $belongsToManyRelation = BelongsToManyRelationFactory::new()->create();

        ModelFactory::new()
            ->hasAttached($belongsToManyRelation, ['number' => 10], 'belongsToManyRelation')
            ->create();
        $matchingModel = ModelFactory::new()
            ->hasAttached($belongsToManyRelation, ['number' => 11], 'belongsToManyRelation')
            ->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        $response = $this->search([
            ['relation' => 'belongsToManyRelation', 'exists' => false, 'nested' => [
                ['field' => 'pivot.number', 'value' => 10],
                ['field' => 'id', 'value' => $belongsToManyRelation->getKey()],
            ]],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel, $matchingModel2], new ModelResource());
    }

    public function test_the_statement_built_for_relation_filters(): void
    {
        $this->search([
            ['relation' => 'hasManyRelation', 'exists' => false, 'nested' => [
                ['field' => 'number', 'operator' => '>=', 'value' => 4],
                ['field' => 'number', 'operator' => '<=', 'value' => 6],
            ]],
            ['relation' => 'belongsToManyRelation', 'type' => 'or', 'nested' => [
                ['field' => 'pivot.number', 'value' => 10],
            ]],
        ])->assertStatus(200);

        $this->assertStringContainsString(
            '(not exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.number >= ? and has_many_relations.number <= ?)'
            .' or exists (select * from belongs_to_many_relations inner join belongs_to_many_relation_model'
            .' on belongs_to_many_relations.id = belongs_to_many_relation_model.belongs_to_many_relation_id'
            .' where models.id = belongs_to_many_relation_model.model_id and belongs_to_many_relation_model.number = ?))',
            $this->searchStatement()
        );
    }

    #[DataProvider('wrongRelationFilterProvider')]
    public function test_getting_a_list_of_resources_filtered_by_wrong_relation_filter(array $filter, array $errors): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->search([$filter]);

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => $errors]);
    }

    public static function wrongRelationFilterProvider(): array
    {
        return [
            'unknown relation' => [
                ['relation' => 'unknown'],
                ['search.filters.0.relation'],
            ],
            'unknown dotted relation' => [
                ['relation' => 'unknown.foo', 'nested' => [['field' => 'id', 'value' => 1]]],
                ['search.filters.0.relation'],
            ],
            'relation not a string' => [
                ['relation' => ['hasManyRelation']],
                ['search.filters.0.relation'],
            ],
            'pivot as relation' => [
                ['relation' => 'belongsToManyRelation.pivot'],
                ['search.filters.0.relation'],
            ],
            'relation with a field' => [
                ['relation' => 'hasManyRelation', 'field' => 'id', 'value' => 1],
                ['search.filters.0.relation'],
            ],
            'exists not a boolean' => [
                ['relation' => 'hasManyRelation', 'exists' => 'nope'],
                ['search.filters.0.exists'],
            ],
            'exists without relation' => [
                ['field' => 'id', 'value' => 1, 'exists' => false],
                ['search.filters.0.exists'],
            ],
            'field of the parent resource' => [
                ['relation' => 'hasManyRelation', 'nested' => [['field' => 'name', 'value' => 'a']]],
                ['search.filters.0.nested.0.field'],
            ],
            'unknown pivot field' => [
                ['relation' => 'belongsToManyRelation', 'nested' => [['field' => 'pivot.unknown', 'value' => 1]]],
                ['search.filters.0.nested.0.field'],
            ],
            'pivot field on a relation without pivot' => [
                ['relation' => 'hasManyRelation', 'nested' => [['field' => 'pivot.number', 'value' => 1]]],
                ['search.filters.0.nested.0.field'],
            ],
            'pivot field outside a relation filter' => [
                ['field' => 'pivot.number', 'value' => 1],
                ['search.filters.0.field'],
            ],
            'relation filter beyond the nesting depth' => [
                ['nested' => [['relation' => 'hasManyRelation', 'nested' => [['field' => 'number', 'value' => 1]]]]],
                ['search.filters.0.nested.0.nested'],
            ],
        ];
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_when_nesting_is_disabled(): void
    {
        config(['rest.search.max_nesting_depth' => 0]);

        $this->search([
            ['relation' => 'hasManyRelation', 'nested' => [['field' => 'number', 'value' => 1]]],
        ])
            ->assertStatus(422)
            ->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.nested']]);
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_without_nested_when_nesting_is_disabled(): void
    {
        config(['rest.search.max_nesting_depth' => 0]);

        $model = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['model_id' => $model->getKey()]);

        $matchingModel = ModelFactory::new()->create()->fresh();

        $response = $this->search([
            ['relation' => 'hasManyRelation', 'exists' => false],
        ]);

        $this->assertResourcePaginated($response, [$matchingModel], new ModelResource());
    }

    public function test_getting_a_list_of_resources_filtered_by_relation_filter_in_scout_mode(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text'    => ['value' => 'text'],
                    'filters' => [
                        ['relation' => 'hasManyRelation', 'exists' => false],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.relation', 'search.filters.0.exists']]);
    }

    /**
     * Search the resource with the given filters.
     *
     * @param array  $filters
     * @param string $uri
     *
     * @return TestResponse
     */
    protected function search(array $filters, string $uri = '/api/models/search'): TestResponse
    {
        $this->statements = [];

        return $this->post(
            $uri,
            ['search' => ['filters' => $filters]],
            ['Accept' => 'application/json']
        );
    }

    /**
     * Find the select that carried the search filters, with the identifier quoting of the
     * current driver and the text cast PostgreSQL adds to a like removed, so the assertion holds on MySQL, PostgreSQL and SQLite alike.
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
