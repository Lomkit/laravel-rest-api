<?php

namespace Lomkit\Rest\Tests\Unit\Exceptions;

use Lomkit\Rest\Exceptions\ExtensionException;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Exceptions\InvalidDocumentationExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Exceptions\InvalidRouteExtensionException;
use Lomkit\Rest\Exceptions\InvalidWriteExtensionException;
use Lomkit\Rest\Fields\FieldReduction;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondAugmentation;
use Lomkit\Rest\Tests\Fixtures\Extensions\BadlyNamedExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ExtensionExceptionTest extends TestCase
{
    #[Test]
    public function it_offers_no_registry_factory_on_a_kind_exception(): void
    {
        $this->assertSame([false, false, false], [
            method_exists(InvalidQueryExtensionException::class, 'notAnExtension'),
            method_exists(InvalidQueryExtensionException::class, 'invalidName'),
            method_exists(InvalidQueryExtensionException::class, 'sharedName'),
        ]);
    }

    #[Test]
    public function it_keeps_the_registry_exception_final(): void
    {
        $this->assertTrue((new ReflectionClass(InvalidExtensionException::class))->isFinal());
    }

    #[Test]
    public function it_gives_the_engine_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidEngineExtensionException::repeatedIndexAttribute('first', 'App\Rest\FirstExtension', 'App\Rest\PostResource', 'owner_id');

        $this->assertSame([true, false, false, true, false], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidEngineExtensionException::class, 'notAClass'),
            $exception->concerns('App\Rest\FirstExtension'),
            $exception->concerns('first'),
        ]);
    }

    #[Test]
    public function it_gives_the_authorization_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidAuthorizationExtensionException::changedRows('acl', 'App\Rest\AclExtension', 'App\Rest\PostResource', 'view');

        $this->assertSame([true, false, false, true, false], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidAuthorizationExtensionException::class, 'notAClass'),
            $exception->concerns('App\Rest\AclExtension'),
            $exception->concerns('acl'),
        ]);
    }

    #[Test]
    public function it_gives_the_field_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidFieldExtensionException::unknownField('mask', 'App\Rest\MaskExtension', 'App\Rest\PostResource', FieldReduction::Hide, 'salary');

        $this->assertSame([true, false, false, true, false], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidFieldExtensionException::class, 'notAClass'),
            $exception->concerns('App\Rest\MaskExtension'),
            $exception->concerns('mask'),
        ]);
    }

    #[Test]
    public function it_gives_the_route_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidRouteExtensionException::invalidMiddleware('plans', 'App\Rest\PlanRoutes', 'App\Rest\PostResource', Endpoint::Search, 'an empty string');

        $this->assertSame([true, false, false, true, false], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidRouteExtensionException::class, 'notAClass'),
            $exception->concerns('App\Rest\PlanRoutes'),
            $exception->concerns('plans'),
        ]);
    }

    #[Test]
    public function it_gives_the_write_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidWriteExtensionException::reservedRefusalStatus('quota', 'App\Rest\WriteQuota', 'App\Rest\PostResource', Endpoint::Mutate, 409);

        $this->assertSame([true, false, false, true, false], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidWriteExtensionException::class, 'notAClass'),
            $exception->concerns('App\Rest\WriteQuota'),
            $exception->concerns('quota'),
        ]);
    }

    #[Test]
    public function it_gives_the_meta_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidMetaExtensionException::instructionsName('instructions', 'App\Rest\PlanMeta', 'App\Rest\PostResource');

        $this->assertSame([true, false, false, true], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidMetaExtensionException::class, 'missingName'),
            $exception->namesExtension('instructions'),
        ]);
    }

    #[Test]
    public function it_gives_the_documentation_extension_exception_the_shared_base_only(): void
    {
        $exception = InvalidDocumentationExtensionException::statusOutOfRange('quota', 'App\Rest\QuotaDocs', 'api.posts.mutate', 302);

        $this->assertSame([true, false, false, true], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidDocumentationExtensionException::class, 'missingName'),
            $exception->namesExtension('quota'),
        ]);
    }

    #[Test]
    public function it_keeps_the_engine_query_refusal_as_the_previous_exception(): void
    {
        $reason = InvalidEngineQueryException::undeclaredAttribute('owner');

        $exception = InvalidEngineExtensionException::refusedEngineCondition('owner', 'App\Rest\OwnerExtension', 'App\Rest\PostResource', $reason);

        $this->assertSame([$reason, true, false], [$exception->getPrevious(), $exception->concerns('App\Rest\OwnerExtension'), $exception->concerns('owner')]);
    }

    #[Test]
    public function it_names_the_extension_of_every_extension_exception(): void
    {
        $query = InvalidQueryExtensionException::invalidColumn('first', FirstAugmentation::class, 'a b', 'App\Rest\PostResource');
        $registry = InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl');

        $this->assertSame([true, true, true, true, false, false], [
            is_a($query, ExtensionException::class),
            is_a($registry, ExtensionException::class),
            $query->concerns(FirstAugmentation::class),
            $registry->concerns(BadlyNamedExtension::class),
            $query->concerns('first'),
            $query->concerns(SecondAugmentation::class),
        ]);
    }
}
