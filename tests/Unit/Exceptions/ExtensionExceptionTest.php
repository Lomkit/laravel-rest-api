<?php

namespace Lomkit\Rest\Tests\Unit\Exceptions;

use Lomkit\Rest\Exceptions\ExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
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

        $this->assertSame([true, false, false, true], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            method_exists(InvalidEngineExtensionException::class, 'missingName'),
            $exception->namesExtension('first'),
        ]);
    }

    #[Test]
    public function it_keeps_the_engine_query_refusal_as_the_previous_exception(): void
    {
        $reason = InvalidEngineQueryException::undeclaredAttribute('owner');

        $exception = InvalidEngineExtensionException::refusedEngineCondition('owner', 'App\Rest\OwnerExtension', 'App\Rest\PostResource', $reason);

        $this->assertSame([$reason, true], [$exception->getPrevious(), $exception->namesExtension('owner')]);
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
