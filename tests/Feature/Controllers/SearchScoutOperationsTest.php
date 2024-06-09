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

class SearchScoutOperationsTest extends TestCase
{
//    public function test_getting_a_list_of_resources_with_scout_with_not_authorized_filters(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/search',
//            [
//                'search' => [
//                    'text' => [
//                        'value' => 'text'
//                    ],
//                    'filters' => [
//                        ['field' => 'name', 'value' => 'value'],
//                    ],
//                ],
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertStatus(422);
//        $response->assertJsonStructure(['message', 'errors' => ['search.filters']]);
//    }

//    public function test_getting_a_list_of_resources_filtered_by_model_field_using_default_operator(): void
//    {
//        $matchingModel = ModelFactory::new()->create(['name' => 'match'])->fresh();
//        ModelFactory::new()->create(['name' => 'not match'])->fresh();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/search',
//            [
//                'search' => [
//                    'filters' => [
//                        ['field' => 'name', 'value' => 'match'],
//                    ],
//                ],
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $this->assertResourcePaginated(
//            $response,
//            [$matchingModel],
//            new ModelResource()
//        );
//    }
}
