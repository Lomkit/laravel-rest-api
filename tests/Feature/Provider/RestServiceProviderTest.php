<?php

namespace Lomkit\Rest\Tests\Feature\Provider;

use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Facades\Rest as RestFacade;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
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
        $this->assertSame(['key', 'names'], array_keys((array) config('rest.gates')));
        $this->assertSame(1, config('rest.limits.depths.filters'));
        $this->assertSame(100, config('rest.limits.sizes.filters'));
        $this->assertSame(1000, config('rest.limits.totals.entries'));
    }

    #[Test]
    public function it_fills_every_limit_of_a_config_published_without_limits(): void
    {
        config()->set('rest', ['gates' => ['key' => 'abilities'], 'precognition' => ['enabled' => true]]);

        $this->app->register(RestServiceProvider::class, force: true);

        $this->assertSame([
            'sizes' => [
                'filters' => 100,
                'sorts' => 20,
                'selects' => 100,
                'scopes' => 10,
                'aggregates' => 20,
                'includes' => 20,
                'instructions' => 10,
                'fields' => 100,
                'values' => 100,
                'mutate' => 100,
                'relation_entries' => 100,
                'resources' => 1000,
            ],
            'depths' => ['filters' => 1, 'includes' => 3, 'mutations' => 3],
            'totals' => ['filters' => 500, 'entries' => 1000],
            'lengths' => ['patterns' => 255],
        ], config('rest.limits'));
        $this->assertSame(1000, $this->context()->limits()->totals->entries);
    }

    #[Test]
    public function it_fills_the_limits_that_a_published_limits_block_leaves_out(): void
    {
        config()->set('rest', ['limits' => ['sizes' => ['filters' => 50], 'depths' => ['filters' => '2']]]);

        $this->app->register(RestServiceProvider::class, force: true);
        $limits = $this->context()->limits();

        $this->assertSame([50, 20, 1000], [$limits->sizes->filters, $limits->sizes->sorts, $limits->sizes->resources]);
        $this->assertSame([2, 3, 3], [$limits->depths->filters, $limits->depths->includes, $limits->depths->mutations]);
        $this->assertSame([500, 1000], [$limits->totals->filters, $limits->totals->entries]);
        $this->assertSame(255, $limits->lengths->patterns);
    }

    #[Test]
    public function it_keeps_an_unknown_limit_of_a_published_config_and_names_it(): void
    {
        config()->set('rest', ['limits' => ['sizes' => ['filter' => 50]]]);

        $this->app->register(RestServiceProvider::class, force: true);

        $this->assertSame(50, config('rest.limits.sizes.filter'));
        $this->expectExceptionObject(new InvalidConfigException('The [rest.limits.sizes.filter] option does not exist.'));

        $this->context()->limits();
    }

    #[Test]
    public function it_resolves_the_facade_and_the_class_to_one_instance(): void
    {
        $rest = $this->app->make(Rest::class);

        $this->assertSame($rest, $this->app->make(Rest::class));
        $this->assertSame($rest, RestFacade::getFacadeRoot());
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/products/search', 'POST'), $this->app);
    }
}
