<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class MalformedEnvelopeOperationsTest extends TestCase
{
    public function test_searching_with_a_null_search_envelope_is_a_validation_error(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            ['search' => null],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search']]);
    }

    public function test_searching_with_a_string_search_envelope_is_a_validation_error(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            ['search' => 'boom'],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['search']]);
    }

    public function test_mutating_with_a_string_mutate_envelope_is_a_validation_error(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/mutate',
            ['mutate' => 'x'],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertExactJsonStructure(['message', 'errors' => ['mutate']]);
    }

    public function test_searching_without_a_search_envelope_is_still_allowed(): void
    {
        Gate::policy(Model::class, GreenPolicy::class);

        $response = $this->post(
            '/api/models/search',
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
    }
}
