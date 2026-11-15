<?php

namespace Lomkit\Rest\Tests\Unit\Exceptions;

use Lomkit\Rest\Exceptions\ExtensionException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
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
            method_exists(InvalidQueryExtensionException::class, 'missingName'),
            method_exists(InvalidQueryExtensionException::class, 'notAClass'),
            method_exists(InvalidQueryExtensionException::class, 'unnamedEntry'),
        ]);
    }

    #[Test]
    public function it_keeps_the_registry_exception_final(): void
    {
        $this->assertTrue((new ReflectionClass(InvalidExtensionException::class))->isFinal());
    }

    #[Test]
    public function it_names_the_extension_of_every_extension_exception(): void
    {
        $query = InvalidQueryExtensionException::invalidColumn('first', 'a b', 'App\Rest\PostResource');
        $registry = InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl');

        $this->assertSame([true, true, true, true, false], [
            is_a($query, ExtensionException::class),
            is_a($registry, ExtensionException::class),
            $query->concerns('first'),
            $registry->concerns(BadlyNamedExtension::class),
            $query->concerns('second'),
        ]);
    }
}
