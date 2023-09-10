<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchSortingOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_sorting_by_unauthorized_relation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'not_authorized_field'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['sorts.0.field']]);
    }

    public function test_getting_a_list_of_resources_sorting_by_id_field(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'id'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_sorting_by_desc_id_field(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'id', 'direction' => 'desc'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel2, $matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_sorting_by_asc_id_field(): void
    {
        $matchingModel = ModelFactory::new()->create()->fresh();
        $matchingModel2 = ModelFactory::new()->create()->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'id', 'direction' => 'asc'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_sorting_by_two_fields_with_first_that_has_no_impact(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 2])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 2])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'number', 'direction' => 'desc'],
                    ['field' => 'id', 'direction' => 'asc'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_sorting_by_two_fields_with_first_that_has_no_impact_desc(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 2])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 2])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'number', 'direction' => 'desc'],
                    ['field' => 'id', 'direction' => 'desc'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel2, $matchingModel],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_sorting_by_two_fields_with_second_that_has_no_impact(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 2])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 3])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'number', 'direction' => 'asc'],
                    ['field' => 'id', 'direction' => 'desc'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel, $matchingModel2],
            new ModelResource()
        );
    }

    public function test_getting_a_list_of_resources_sorting_by_two_fields_with_second_that_has_no_impact_desc(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 2])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => 3])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'sorts' => [
                    ['field' => 'number', 'direction' => 'desc'],
                    ['field' => 'id', 'direction' => 'desc'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel2, $matchingModel],
            new ModelResource()
        );
    }
}
