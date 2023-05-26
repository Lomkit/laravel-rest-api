<?php

namespace Lomkit\Rest\Tests\Support\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Response;

trait InteractsWithAuthorization
{
    protected function withAuthenticatedUser($user = null, string $driver = 'api')
    {
        return $this->actingAs($user ?? $this->resolveAuthFactoryClass()::new()->create(), $driver);
    }

    protected function resolveAuthFactoryClass()
    {
        return null;
    }


    protected function assertUnauthorizedResponse($response)
    {
        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }
}