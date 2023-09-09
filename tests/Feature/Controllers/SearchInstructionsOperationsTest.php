<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Bus\PendingBatch;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\CallRestApiAction;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class SearchInstructionsOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_using_unauthorized_instruction(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'instructions' => [
                    ['name' => 'not_authorized_instruction'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['instructions.0.name']]);
    }

    public function test_getting_a_list_of_resources_instructing_numbered(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        $matchingModel2 = ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'instructions' => [
                    ['name' => 'numbered'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource(),
        );
    }

    public function test_getting_a_list_of_resources_instructing_numbered_with_unauthorized_fields(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'instructions' => [
                    [
                        'name'   => 'numbered',
                        'fields' => [
                            ['name' => 'unauthorized_field', 'value' => 1],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['instructions.0.fields.0.name']]);
    }

    public function test_getting_a_list_of_resources_instructing_numbered_with_unauthorized_validation(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 1])->fresh();
        ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'instructions' => [
                    [
                        'name'   => 'numbered',
                        'fields' => [
                            ['name' => 'number', 'value' => 'unauthorized_string'],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['instructions.0.fields.0.value']]);
    }

    public function test_getting_a_list_of_resources_instructing_numbered_with_fields(): void
    {
        $matchingModel = ModelFactory::new()->create(['number' => 11])->fresh();
        ModelFactory::new()->create(['number' => 5])->fresh();
        ModelFactory::new()->create(['number' => -1])->fresh();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'instructions' => [
                    [
                        'name'   => 'numbered',
                        'fields' => [
                            ['name' => 'number', 'value' => 10],
                        ],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $this->assertResourcePaginated(
            $response,
            [$matchingModel],
            new ModelResource(),
        );
    }
//    public function test_operate_action(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/modify-number',
//            [],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertJson([
//            'data' => [
//                'impacted' => 2
//            ]
//        ]);
//        $this->assertEquals(
//            2,
//            Model::where('number', 100000000)->count()
//        );
//    }
//
//    public function test_operate_not_found_action(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/not-found-action',
//            [],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertStatus(404);
//    }
//
//    public function test_operate_mass_action(): void
//    {
//        ModelFactory::new()->count(150)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/modify-number',
//            [],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertJson([
//            'data' => [
//                'impacted' => 150
//            ]
//        ]);
//        $this->assertEquals(
//            150,
//            Model::where('number', 100000000)->count()
//        );
//    }
//
//    public function test_operate_action_with_unauthorized_fields(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/modify-number',
//            [
//                'fields' => [
//                    'unauthorized_field' => 100000001
//                ]
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertStatus(422);
//        $response->assertJsonStructure(['message', 'errors' => ['fields']]);
//    }
//
//    public function test_operate_action_with_fields(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/modify-number',
//            [
//                'fields' => [
//                    'number' => 100000001
//                ]
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertJson([
//            'data' => [
//                'impacted' => 2
//            ]
//        ]);
//        $this->assertEquals(
//            2,
//            Model::where('number', 100000001)->count()
//        );
//    }
//
//    public function test_operate_mass_action_with_fields(): void
//    {
//        ModelFactory::new()->count(150)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/modify-number',
//            [
//                'fields' => [
//                    'number' => 100000001
//                ]
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertJson([
//            'data' => [
//                'impacted' => 150
//            ]
//        ]);
//        $this->assertEquals(
//            150,
//            Model::where('number', 100000001)->count()
//        );
//    }
//
//    public function test_operate_queueable_action(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/queueable-modify-number',
//            [],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertJson([
//            'data' => [
//                'impacted' => 2
//            ]
//        ]);
//        $this->assertEquals(
//            2,
//            Model::where('number', 100000000)->count()
//        );
//    }
//
//    public function test_operate_catched_queueable_action(): void
//    {
//        ModelFactory::new()->count(2)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        \Illuminate\Support\Facades\Queue::fake();
//
//        $response = $this->post(
//            '/api/models/actions/queueable-modify-number',
//            [],
//            ['Accept' => 'application/json']
//        );
//
//        \Illuminate\Support\Facades\Queue::assertPushedOn('custom-queue', CallRestApiAction::class);
//    }
//
//    public function test_operate_action_with_search(): void
//    {
//        ModelFactory::new()
//            ->create([
//                'string' => 'match'
//            ]);
//
//        ModelFactory::new()->count(2)
//            ->create();
//
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        $response = $this->post(
//            '/api/models/actions/modify-number',
//            [
//                'search' => [
//                    'filters' => [
//                        ['field' => 'string', 'value' => 'match']
//                    ]
//                ]
//            ],
//            ['Accept' => 'application/json']
//        );
//
//        $response->assertJson([
//            'data' => [
//                'impacted' => 1
//            ]
//        ]);
//        $this->assertEquals(
//            1,
//            Model::where('number', 100000000)->count()
//        );
//    }
//
//    public function test_operate_batchable_action(): void
//    {
//        ModelFactory::new()->count(150)->create();
//
//        Gate::policy(Model::class, GreenPolicy::class);
//
//        Bus::fake();
//
//        $response = $this->post(
//            '/api/models/actions/batchable-modify-number',
//            [],
//            ['Accept' => 'application/json']
//        );
//
//
//        Bus::assertBatched(function (PendingBatch $batch) {
//            return $batch->name == 'batchable-modify-number' &&
//                $batch->jobs->count() === 2;
//        });
//    }
}
