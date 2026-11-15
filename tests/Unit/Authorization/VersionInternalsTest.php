<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\CollectedVersions;
use Lomkit\Rest\Authorization\DecisionEntries;
use Lomkit\Rest\Authorization\DecisionVersions;
use Lomkit\Rest\Authorization\DependentVersions;
use Lomkit\Rest\Authorization\RowVersions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class VersionInternalsTest extends TestCase
{
    /** @param class-string $class */
    #[Test]
    #[DataProvider('collaborators')]
    public function it_marks_the_version_collaborators_internal(string $class): void
    {
        $this->assertStringContainsString('@internal', (string) (new ReflectionClass($class))->getDocComment());
    }

    #[Test]
    public function it_keeps_the_constructor_of_the_versions_internal_and_the_class_public(): void
    {
        $this->assertStringContainsString('@internal', (string) (new ReflectionMethod(RowVersions::class, '__construct'))->getDocComment());
        $this->assertStringNotContainsString('@internal', (string) (new ReflectionClass(RowVersions::class))->getDocComment());
    }

    /** @return array<string, array{class-string}> */
    public static function collaborators(): array
    {
        return [
            'the versions of one batch' => [CollectedVersions::class],
            'the versions of the rows' => [DependentVersions::class],
            'the hook and the extensions' => [DecisionVersions::class],
            'the keys of a store round' => [DecisionEntries::class],
        ];
    }
}
