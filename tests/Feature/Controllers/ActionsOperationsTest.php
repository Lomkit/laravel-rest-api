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
        $response->assertJsonStructure(['message', 'errors' => ['fields.0.name']]);
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
        $response->assertJsonStructure(['message', 'errors' => ['fields.0.value']]);
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
}
