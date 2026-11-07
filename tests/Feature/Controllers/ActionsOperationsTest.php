<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
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
}
