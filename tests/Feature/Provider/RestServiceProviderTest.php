<?php

namespace Lomkit\Rest\Tests\Feature\Provider;

use Lomkit\Rest\Facades\Rest as RestFacade;
use Lomkit\Rest\Rest;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RestServiceProviderTest extends PackageTestCase
{
    #[Test]
    public function it_fills_the_nested_defaults_that_the_application_config_leaves_out(): void
    {
        config()->set('rest', ['gates' => ['key' => 'abilities', 'names' => ['authorized_to_view' => 'can_view']]]);

        $this->app->register(RestServiceProvider::class, force: true);

        $this->assertSame('abilities', config('rest.gates.key'));
        $this->assertSame('can_view', config('rest.gates.names.authorized_to_view'));
        $this->assertSame('authorized_to_create', config('rest.gates.names.authorized_to_create'));
        $this->assertFalse(config('rest.gates.message.enabled'));
        $this->assertTrue(config('rest.gates.enabled'));
        $this->assertSame(1, config('rest.search.max_nesting_depth'));
    }

    #[Test]
    public function it_resolves_the_facade_and_the_class_to_one_instance(): void
    {
        $rest = $this->app->make(Rest::class);

        $this->assertSame($rest, $this->app->make(Rest::class));
        $this->assertSame($rest, RestFacade::getFacadeRoot());
    }
}
