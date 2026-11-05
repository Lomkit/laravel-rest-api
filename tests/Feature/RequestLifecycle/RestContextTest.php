<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use DateTimeInterface;
use Illuminate\Http\Request;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Catalog\StaffProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RestContextTest extends PackageTestCase
{
    #[Test]
    public function it_computes_the_fields_of_each_context_for_its_own_user(): void
    {
        $staffContext = $this->contextFor(User::factory()->staff()->make());
        $customerContext = $this->contextFor(User::factory()->make());

        $this->assertSame(
            ['id', 'name', 'price'],
            $staffContext->resource(StaffProductResource::class)->getFields()->names(),
        );
        $this->assertSame(
            ['id', 'name'],
            $customerContext->resource(StaffProductResource::class)->getFields()->names(),
        );
    }

    #[Test]
    public function it_gives_one_resource_instance_per_class_within_a_context(): void
    {
        $context = $this->contextFor(null);

        $this->assertSame($context->resource(ProductResource::class), $context->resource(ProductResource::class));
        $this->assertNotSame(
            $context->resource(ProductResource::class),
            $this->contextFor(null)->resource(ProductResource::class),
        );
    }

    #[Test]
    public function it_resolves_the_resource_of_a_relation_through_the_same_context(): void
    {
        $context = $this->contextFor(null);

        $category = $context->resource(ProductResource::class)->relation('category')->resource();

        $this->assertSame($context->resource(CategoryResource::class), $category);
    }

    #[Test]
    public function it_gives_a_resource_created_directly_a_context_from_the_current_request(): void
    {
        $this->app->instance('request', Request::create('/api/products/search', 'POST', ['limit' => '25']));

        $request = (new ProductResource)->context()->request;

        $this->assertInstanceOf(RestRequest::class, $request);
        $this->assertSame('25', $request->input('limit'));
    }

    #[Test]
    public function it_keeps_a_resource_serializable_with_its_context(): void
    {
        $resource = $this->contextFor(User::factory()->make())->resource(ProductResource::class);

        $restored = unserialize(serialize($resource));

        $this->assertInstanceOf(RestRequest::class, $restored->context()->request);
    }

    #[Test]
    public function it_checks_a_field_against_the_user_of_its_context(): void
    {
        $staff = $this->contextFor(User::factory()->staff()->make())->resource(StaffProductResource::class);
        $customer = $this->contextFor(User::factory()->make())->resource(StaffProductResource::class);

        $this->assertNotNull(FieldPath::resolve($staff, 'price'));
        $this->assertNull(FieldPath::resolve($customer, 'price'));
    }

    #[Test]
    public function it_reads_the_limits_once_per_request(): void
    {
        config(['rest.limits.depths.filters' => 3]);
        $context = $this->contextFor(null);

        $first = $context->limits()->depths->filters;
        config(['rest.limits.depths.filters' => 5]);

        $this->assertSame([3, 3, 5], [$first, $context->limits()->depths->filters, $this->contextFor(null)->limits()->depths->filters]);
        $this->assertSame($context->limits(), $context->limits());
    }

    #[Test]
    public function it_reads_the_config_once_per_request(): void
    {
        config(['rest.gates.key' => 'abilities']);
        $context = $this->contextFor(null);

        $first = $context->config()->gatesKey;
        config(['rest.gates.key' => 'permissions']);

        $this->assertSame(['abilities', 'abilities', 'permissions'], [$first, $context->config()->gatesKey, $this->contextFor(null)->config()->gatesKey]);
        $this->assertSame($context->config(), $context->config());
    }

    #[Test]
    public function it_names_the_key_of_a_config_value_of_the_wrong_type(): void
    {
        config(['rest.authorizations.cache.default' => 'five']);

        $this->expectExceptionObject(new InvalidConfigException('The [rest.authorizations.cache.default] option must be an integer of 0 or more.'));

        $this->contextFor(null)->config();
    }

    #[Test]
    public function it_keeps_the_decisions_for_the_minutes_of_the_config(): void
    {
        config(['rest.authorizations.cache.default' => '15']);
        $this->travelTo('2026-11-01 10:00:00');

        $resource = $this->contextFor(null)->resource(ProductResource::class);
        $expiry = $resource->cacheAuthorizationFor();

        $this->assertInstanceOf(DateTimeInterface::class, $expiry);
        $this->assertSame('2026-11-01 10:15:00', $expiry->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_reads_a_filter_depth_given_as_a_numeric_string(): void
    {
        config(['rest.limits.depths.filters' => '2']);

        $this->assertSame(2, $this->contextFor(null)->limits()->depths->filters);
    }

    private function contextFor(?User $user): RestContext
    {
        $request = RestRequest::create('/api/products/search', 'POST');
        $request->setUserResolver(fn (): ?User => $user);

        return new RestContext($request, $this->app);
    }
}
