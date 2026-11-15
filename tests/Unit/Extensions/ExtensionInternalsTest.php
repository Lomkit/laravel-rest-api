<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Lomkit\Rest\Console\Check\ExtensionCheck;
use Lomkit\Rest\Console\Check\ExtensionReport;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\ExtensionName;
use Lomkit\Rest\Extensions\ExtensionOrigin;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ExtensionInternalsTest extends TestCase
{
    /** @param class-string $class */
    #[Test]
    #[DataProvider('collaborators')]
    public function it_marks_the_extension_collaborators_internal(string $class): void
    {
        $this->assertStringContainsString('@internal', (string) (new ReflectionClass($class))->getDocComment());
    }

    #[Test]
    public function it_keeps_the_registry_free_of_an_emptiness_check(): void
    {
        $this->assertFalse(method_exists(ExtensionRegistry::class, 'isEmpty'));
    }

    /** @return array<string, array{class-string}> */
    public static function collaborators(): array
    {
        return [
            'the runtime store' => [RuntimeExtensions::class],
            'the merged list' => [ExtensionList::class],
            'an entry of the list' => [ExtensionEntry::class],
            'the factory' => [ExtensionFactory::class],
            'the name rule' => [ExtensionName::class],
            'the origin of an entry' => [ExtensionOrigin::class],
            'the rest:check check' => [ExtensionCheck::class],
            'the rest:check report' => [ExtensionReport::class],
        ];
    }
}
