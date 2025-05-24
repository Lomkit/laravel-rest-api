<?php

namespace Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Models\HasOneRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class SearchLimitingOperationsTest extends TestCase
{
    public function test_getting_a_list_of_resources_with_include_and_unauthorized_limit(): void
    {
        ModelFactory::new()->count(2)->create();

        Gate::policy(Model::class, GreenPolicy::class);
        Gate::policy(HasOneRelation::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [
                'search' => [
                    'includes' => [
                        ['relation' => 'hasOneRelation'],
                    ],
                    'limit' => 999,
                ],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search.limit']]);
    }
}
