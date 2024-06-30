<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchScoutOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scout.driver', 'null');
    }

    public function test_getting_a_list_of_resources_with_not_compatible_resource(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.text.value']]);
    }

    public function test_getting_a_list_of_resources_with_not_allowed_filter_operator(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'filters' => [
                        ['field' => 'id', 'operator' => '>=', 'value' => 2],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.filters.0.operator']]);
    }

    public function test_getting_a_list_of_resources_with_not_allowed_filter_field(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'filters' => [
                        ['field' => 'id', 'operator' => '=', 'value' => 2],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.filters.0.field']]);
    }

    public function test_getting_a_list_of_resources_with_not_allowed_sort_field(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'sorts' => [
                        ['field' => 'id'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.sorts.0.field']]);
    }

    public function test_getting_a_list_of_resources_with_not_allowed_instruction(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'instructions' => [
                        ['name' => 'not_authorized_instruction'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.instructions.0.name']]);
    }

    public function test_getting_a_list_of_resources_with_allowed_filter_field(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'filters' => [
                        ['field' => 'allowed_scout_field', 'operator' => '=', 'value' => 2],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_with_allowed_sort_field(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'sorts' => [
                        ['field' => 'allowed_scout_field'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_with_allowed_instruction(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'instructions' => [
                        ['name' => 'numbered'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_with_not_allowed_filter_nested(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'filters' => [
                        [
                            'nested' => [
                                ['field' => 'allowed_scout_field', 'value' => 1],
                                ['field' => 'allowed_scout_field', 'value' => 2],
                            ],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.filters.0.nested']]);
    }

    public function test_getting_a_list_of_resources_with_not_allowed_filter_type(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'filters' => [
                        ['field' => 'allowed_scout_field', 'operator' => '=', 'value' => 2, 'type' => 'or'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.filters.0.type']]);
    }

    public function test_getting_a_list_of_resources_with_not_allowed_scopes(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                    'scopes' => [
                        ['name' => 'numbered'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['search.scopes']]);
    }

    public function test_getting_a_list_of_resources_with_scout(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/searchable-models/search',
            [
                'search' => [
                    'text' => [
                        'value' => 'text',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [],
            new ModelResource()
        );
    }
}
