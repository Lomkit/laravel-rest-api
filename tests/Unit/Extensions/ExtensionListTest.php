<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\ExtensionOrigin;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\InjectedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtensionListTest extends TestCase
{
    #[Test]
    public function it_merges_the_config_over_the_runtime_registrations(): void
    {
        $list = ExtensionList::merge(
            ['noting' => BothKindsExtension::class, 'archive' => InjectedExtension::class],
            ['noting' => NotingExtension::class, 'counting' => CountingExtension::class],
        );

        $this->assertEquals([
            new ExtensionEntry('noting', BothKindsExtension::class, ExtensionOrigin::Config, false),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Runtime, false),
            new ExtensionEntry('archive', InjectedExtension::class, ExtensionOrigin::Config, false),
        ], $list->entries);
        $this->assertSame([
            'noting' => BothKindsExtension::class,
            'counting' => CountingExtension::class,
            'archive' => InjectedExtension::class,
        ], $list->enabled());
    }

    #[Test]
    public function it_disables_a_runtime_extension_with_false(): void
    {
        $list = ExtensionList::merge(['noting' => false], ['noting' => NotingExtension::class, 'counting' => CountingExtension::class]);

        $this->assertEquals([
            new ExtensionEntry('noting', NotingExtension::class, ExtensionOrigin::Runtime, true),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Runtime, false),
        ], $list->entries);
        $this->assertSame(['counting' => CountingExtension::class], $list->enabled());
    }

    #[Test]
    public function it_keeps_a_disabled_entry_that_disables_nothing(): void
    {
        $list = ExtensionList::merge(['tenant' => false], []);

        $this->assertEquals([new ExtensionEntry('tenant', null, ExtensionOrigin::Config, true)], $list->entries);
        $this->assertSame([], $list->enabled());
    }

    #[Test]
    public function it_keeps_the_runtime_registrations_without_config(): void
    {
        $list = ExtensionList::merge([], ['noting' => NotingExtension::class]);

        $this->assertEquals([new ExtensionEntry('noting', NotingExtension::class, ExtensionOrigin::Runtime, false)], $list->entries);
        $this->assertSame(['noting' => NotingExtension::class], $list->enabled());
    }

    #[Test]
    public function it_refuses_a_config_list_without_names(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::missingName(NotingExtension::class));

        ExtensionList::merge([NotingExtension::class], []);
    }

    #[Test]
    public function it_refuses_an_invalid_config_name(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::invalidName('Acl'));

        ExtensionList::merge(['Acl' => NotingExtension::class], []);
    }

    #[Test]
    #[DataProvider('neitherClassNamesNorFalse')]
    public function it_refuses_a_config_value_that_is_neither_a_class_name_nor_false(mixed $value, string $type): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClass('acl', $type));

        ExtensionList::merge(['acl' => $value], []);
    }

    /** @return array<string, array{mixed, string}> */
    public static function neitherClassNamesNorFalse(): array
    {
        return [
            'true' => [true, 'bool'],
            'an integer' => [1, 'int'],
            'an array' => [[], 'array'],
            'null' => [null, 'null'],
            'an instance' => [new NotingExtension, NotingExtension::class],
        ];
    }
}
