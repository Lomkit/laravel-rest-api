<?php

namespace Lomkit\Rest\Tests\Feature;

use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Lomkit\Rest\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Rest\Tests\Support\Models\User;
use Lomkit\Rest\Tests\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Rest\Tests\Support\Traits\InteractsWithAuthorization;
use Lomkit\Rest\Tests\Support\Traits\InteractsWithResource;

class TestCase extends BaseTestCase
{
    use InteractsWithAuthorization, InteractsWithResource;

    // @TODO test query builder

    protected function setUp(): void
    {
        parent::setUp();

        $this->withAuthenticatedUser();
    }

    protected function resolveAuthFactoryClass()
    {
        return UserFactory::class;
    }
}