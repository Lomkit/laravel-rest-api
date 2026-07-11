<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\CallRestApiAction;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class ActionsOperationsTest extends TestCase
{
    public function test_operate_action(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 2,
            ],
        ]);
        $this->assertEquals(
            2,
            Model::where('number', 100000000)->count()
        );
    }

    public function test_operate_standalone_action(): void
    {
        $model = ModelFactory::new()->create();
        ModelFactory::new()->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/standalone-modify-number',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 0,
            ],
        ]);
        $this->assertEquals(
            1,
            Model::where('number', 100000000)->count()
        );
    }

    public function test_operate_standalone_action_with_fields(): void
    {
        $model = ModelFactory::new()->create();
        ModelFactory::new()->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/standalone-modify-number',
            [
                'fields' => [
                    ['name' => 'number', 'value' => 100000001],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 0,
            ],
        ]);
        $this->assertEquals(
            1,
            Model::where('number', 100000001)->count()
        );
    }

    public function test_operate_not_found_action(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/not-found-action',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(404);
    }

    public function test_operate_mass_action(): void
    {
        ModelFactory::new()->count(150)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 150,
            ],
        ]);
        $this->assertEquals(
            150,
            Model::where('number', 100000000)->count()
        );
    }

    public function test_operate_action_with_unauthorized_fields(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'fields' => [
                    ['name' => 'unauthorized_field', 'value' => 100000001],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['fields.0.name']]);
    }

    public function test_operate_action_with_unauthorized_field_validation(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'fields' => [
                    ['name' => 'number', 'value' => 1],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['fields.0.value']]);
    }

    public function test_operate_action_with_fields(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'fields' => [
                    ['name' => 'number', 'value' => 100000001],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 2,
            ],
        ]);
        $this->assertEquals(
            2,
            Model::where('number', 100000001)->count()
        );
    }

    public function test_operate_mass_action_with_fields(): void
    {
        ModelFactory::new()->count(150)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'fields' => [
                    ['name' => 'number', 'value' => 100000001],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 150,
            ],
        ]);
        $this->assertEquals(
            150,
            Model::where('number', 100000001)->count()
        );
    }

    public function test_operate_queueable_action(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/queueable-modify-number',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 2,
            ],
        ]);
        $this->assertEquals(
            2,
            Model::where('number', 100000000)->count()
        );
    }

    public function test_operate_catched_queueable_action(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->post(
            '/api/models/actions/queueable-modify-number',
            [],
            ['Accept' => 'application/json']
        );

        \Illuminate\Support\Facades\Queue::assertPushedOn('custom-queue', CallRestApiAction::class);
    }

    public function test_operate_action_with_search(): void
    {
        ModelFactory::new()
            ->create([
                'string' => 'match',
            ]);

        ModelFactory::new()->count(2)
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'string', 'value' => 'match'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 1,
            ],
        ]);
        $this->assertEquals(
            1,
            Model::where('number', 100000000)->count()
        );
    }

    public function test_operate_action_with_search_and_limit(): void
    {
        ModelFactory::new()
            ->count(300)
            ->create([
                'string' => 'match',
            ]);

        ModelFactory::new()->count(2)
            ->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'search' => [
                    'filters' => [
                        ['field' => 'string', 'value' => 'match'],
                    ],
                    'limit' => 150,
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 150,
            ],
        ]);
        $this->assertEquals(
            150,
            Model::where('number', 100000000)->count()
        );
    }

    public function test_operate_batchable_action(): void
    {
        ModelFactory::new()->count(150)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        Bus::fake();

        $response = $this->post(
            '/api/models/actions/batchable-modify-number',
            [],
            ['Accept' => 'application/json']
        );

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->name == 'batchable-modify-number' &&
                $batch->jobs->count() === 2;
        });
    }

    public function test_operate_restricted_action_without_resources_is_rejected(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/restricted-modify-number',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('resources');
        $this->assertEquals(0, Model::where('number', 100000000)->count());
    }

    public function test_operate_restricted_action_with_empty_resources_is_rejected(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/restricted-modify-number',
            [
                'resources' => [],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('resources');
        $this->assertEquals(0, Model::where('number', 100000000)->count());
    }

    public function test_operate_restricted_action_with_unknown_resource_is_rejected(): void
    {
        ModelFactory::new()->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/restricted-modify-number',
            [
                'resources' => [999999],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('resources.0');
        $this->assertEquals(0, Model::where('number', 100000000)->count());
    }

    public function test_operate_restricted_action_impacts_only_the_given_resources(): void
    {
        $first = ModelFactory::new()->create();
        $second = ModelFactory::new()->create();
        $third = ModelFactory::new()->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/restricted-modify-number',
            [
                'resources' => [$first->getKey(), $third->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 2,
            ],
        ]);
        $this->assertEquals(100000000, $first->fresh()->number);
        $this->assertEquals(100000000, $third->fresh()->number);
        $this->assertNotEquals(100000000, $second->fresh()->number);
    }

    public function test_operate_restricted_action_intersects_resources_and_search(): void
    {
        $matchInScope = ModelFactory::new()->create(['string' => 'match']);
        $matchOutOfScope = ModelFactory::new()->create(['string' => 'match']);
        $noMatchInScope = ModelFactory::new()->create(['string' => 'nomatch']);

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/restricted-modify-number',
            [
                'resources' => [$matchInScope->getKey(), $noMatchInScope->getKey()],
                'search'    => [
                    'filters' => [
                        ['field' => 'string', 'value' => 'match'],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertJson([
            'data' => [
                'impacted' => 1,
            ],
        ]);
        $this->assertEquals(100000000, $matchInScope->fresh()->number);
        $this->assertNotEquals(100000000, $noMatchInScope->fresh()->number);
        $this->assertNotEquals(100000000, $matchOutOfScope->fresh()->number);
    }

    public function test_operate_classic_action_prohibits_resources(): void
    {
        $model = ModelFactory::new()->create();

        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/actions/modify-number',
            [
                'resources' => [$model->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('resources');
    }

    public function test_restricted_flag_is_exposed_in_the_resource_schema(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->get(
            '/api/models',
            ['Accept' => 'application/json']
        );

        $response->assertJsonFragment([
            'uriKey'     => 'restricted-modify-number',
            'restricted' => true,
        ]);
        $response->assertJsonFragment([
            'uriKey'     => 'modify-number',
            'restricted' => false,
        ]);
    }
}
