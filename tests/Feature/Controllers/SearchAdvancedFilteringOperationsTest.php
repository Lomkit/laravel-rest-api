<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Rules\Search\Search;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelWithFiltersResource;
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

    public function test_getting_a_list_of_resources_filtered_by_filter_class(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'name' => 'match', 'string' => 'other'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 1, 'name' => 'other', 'string' => 'match'])->fresh();
        // Would be returned if the filter was not applied as a single condition
        ModelFactory::new()->create(['number' => 2, 'name' => 'other', 'string' => 'match']);
        ModelFactory::new()->create(['number' => 1, 'name' => 'other', 'string' => 'other']);

        $response = $this->searchWithExtensions([
            ['field' => 'number', 'value' => 1],
            ['field' => 'name_or_string', 'value' => 'match'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelWithFiltersResource()
        );

        $this->assertStringContainsString(
            '(models.number = ? and (name = ? or string = ?))',
            $this->searchStatement()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_filter_class_using_or_type_and_operator(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'name' => 'other', 'string' => 'other'])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 2, 'name' => 'other', 'string' => 'a match'])->fresh();
        ModelFactory::new()->create(['number' => 2, 'name' => 'other', 'string' => 'other']);

        $response = $this->searchWithExtensions([
            ['field' => 'number', 'value' => 1],
            ['field' => 'name_or_string', 'operator' => 'like', 'value' => '%match', 'type' => 'or'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelWithFiltersResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_filter_class_inside_nested(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1, 'name' => 'match'])->fresh();
        ModelFactory::new()->create(['number' => 2, 'name' => 'match']);

        $response = $this->searchWithExtensions([
            [
                'nested' => [
                    ['field' => 'name_or_string', 'value' => 'match'],
                    ['field' => 'number', 'value' => 1],
                ],
            ],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelWithFiltersResource()
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_filter_class_on_a_resource_not_declaring_it(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->search([
            ['field' => 'name_or_string', 'value' => 'match'],
        ]);

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.field']]);
    }

    public function test_getting_a_list_of_resources_filtered_by_unknown_field_on_a_resource_declaring_filter_classes(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->searchWithExtensions([
            ['field' => 'not_a_filter', 'value' => 'match'],
        ]);

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters.0.field']]);
        $this->assertSame(
            ['search.filters.0.field' => ['The \'field\' field is not valid.']],
            $response->json('errors')
        );
    }

    public function test_getting_a_list_of_resources_filtered_by_filter_class_declared_under_the_name_of_a_field(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
        ModelFactory::new()->create(['name' => 'other']);

        $response = $this->searchWithExtensions([
            ['field' => 'unique', 'value' => 'match'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelWithFiltersResource()
        );

        $this->assertStringContainsString('((name = ? or string = ?))', $this->searchStatement());
    }

    public function test_filter_classes_are_not_exposed_as_fields(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->post(
            '/api/model-with-filters/search',
            [
                'search' => [
                    'selects' => [
                        ['field' => 'name_or_string'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.selects.0.field']]);
    }

    public function test_getting_a_list_of_resources_rejected_by_the_resource_after_search_validation(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->searchWithExtensions([
            ['field' => 'name', 'value' => 'match'],
            ['field' => 'name_or_string', 'value' => 'match'],
        ]);

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters']]);
        $this->assertSame(
            ['search.filters' => ['The name and name_or_string filters cannot be combined.']],
            $response->json('errors')
        );
    }

    public function test_the_resource_after_search_validation_is_skipped_for_a_search_failing_its_rules(): void
    {
        ModelFactory::new()->count(2)->create();

        // The hook of the resource reads the filters as an array and would break on this one
        $response = $this->post(
            '/api/model-with-filters/search',
            ['search' => ['filters' => 'x']],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.filters']]);
    }

    public function test_operating_an_action_with_a_search_rejected_by_the_resource_after_search_validation(): void
    {
        ModelFactory::new()->count(2)->create();

        $response = $this->post(
            '/api/model-with-filters/actions/modify-number',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'name', 'value' => 'match'],
                        ['field' => 'name_or_string', 'value' => 'match'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $this->assertSame(
            ['search.filters' => ['The name and name_or_string filters cannot be combined.']],
            $response->json('errors')
        );
    }

    public function test_the_resource_after_search_validation_is_skipped_for_a_search_that_is_not_an_array(): void
    {
        $resource = new class() extends ModelResource {
            public bool $inspected = false;

            public function afterSearchValidation(RestRequest $request, Validator $validator): void
            {
                $this->inspected = true;
            }
        };

        // An action does not require its search to be an array before handing it to the rule
        (new Search())
            ->setResource($resource)
            ->setValidator(ValidatorFacade::make(['search' => 'x'], []))
            ->validate('search', 'x', function () {});

        $this->assertFalse($resource->inspected);
    }

    public function test_getting_a_list_of_resources_accepted_by_the_resource_after_search_validation(): void
    {
        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
        ModelFactory::new()->create(['name' => 'other']);

        $response = $this->searchWithExtensions([
            ['field' => 'name', 'value' => 'match'],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelWithFiltersResource()
        );
    }

    #[DataProvider('negatedRelationFilterProvider')]
    public function test_getting_a_list_of_resources_filtered_by_negated_relation_field_read_as_absence(string $operator, mixed $value): void
    {
        // Holds a related row equal to 5, so it is excluded even though it also holds a 6
        $mixedModel = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $mixedModel->getKey()]);
        HasManyRelationFactory::new()->create(['number' => 6, 'model_id' => $mixedModel->getKey()]);

        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 6, 'model_id' => $matchingModel->getKey()]);

        // Holds no related row at all, so none of them equals 5
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        $response = $this->searchWithExtensions([
            ['field' => 'hasManyRelation.number', 'operator' => $operator, 'value' => $value],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelWithFiltersResource()
        );
    }

    public static function negatedRelationFilterProvider(): array
    {
        return [
            '!='          => ['!=', 5],
            'not in'      => ['not in', [5]],
            'not between' => ['not between', [5, 5]],
        ];
    }

    public function test_getting_a_list_of_resources_filtered_by_positive_relation_field_with_negations_read_as_absence(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        HasManyRelationFactory::new()->create(['number' => 5, 'model_id' => $matchingModel->getKey()]);
        HasManyRelationFactory::new()->create(['number' => 6, 'model_id' => $matchingModel->getKey()]);

        $otherModel = ModelFactory::new()->create();
        HasManyRelationFactory::new()->create(['number' => 6, 'model_id' => $otherModel->getKey()]);

        ModelFactory::new()->create();

        $response = $this->searchWithExtensions([
            ['field' => 'hasManyRelation.number', 'value' => 5],
        ]);

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelWithFiltersResource()
        );
    }

    public function test_the_statement_built_for_negated_relation_field_read_as_absence(): void
    {
        $this->searchWithExtensions([
            ['field' => 'hasManyRelation.number', 'operator' => '!=', 'value' => 5],
            ['field' => 'hasManyRelation.id', 'operator' => 'not in', 'value' => [1, 2], 'type' => 'or'],
            ['field' => 'hasManyRelation.number', 'operator' => '>', 'value' => 1],
        ])->assertStatus(200);

        $this->assertStringContainsString(
            '(not exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.number = ?)'
            .' or not exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.id in (?, ?))'
            .' and exists (select * from has_many_relations where models.id = has_many_relations.model_id'
            .' and has_many_relations.number > ?))',
            $this->searchStatement()
        );
    }

    public function test_the_default_resource_keeps_its_details(): void
    {
        $this->assertSame([], (new ModelResource())->filters(app(RestRequest::class)));
        $this->assertArrayNotHasKey('filters', (new ModelWithFiltersResource())->jsonSerialize());
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
     * Search the resource declaring a filter class, a validation hook and a relation reading negations as absence.
     *
     * @param array $filters
     *
     * @return TestResponse
     */
    protected function searchWithExtensions(array $filters): TestResponse
    {
        return $this->search($filters, '/api/model-with-filters/search');
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
