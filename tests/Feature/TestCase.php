<?php

namespace Lomkit\Rest\Tests\Feature;

use Lomkit\Rest\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Rest\Tests\Support\Traits\InteractsWithAuthorization;
use Lomkit\Rest\Tests\Support\Traits\InteractsWithResource;
use Lomkit\Rest\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use InteractsWithAuthorization;
    use InteractsWithResource;

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
