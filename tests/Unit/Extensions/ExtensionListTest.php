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
            ['noting' => [NotingExtension::class], 'counting' => [CountingExtension::class]],
        );

        $this->assertEquals([
            new ExtensionEntry('noting', BothKindsExtension::class, ExtensionOrigin::Config, false, [NotingExtension::class]),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Runtime, false, [CountingExtension::class]),
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
        $list = ExtensionList::merge(['noting' => false], ['noting' => [NotingExtension::class], 'counting' => [CountingExtension::class]]);

        $this->assertEquals([
            new ExtensionEntry('noting', NotingExtension::class, ExtensionOrigin::Runtime, true, [NotingExtension::class]),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Runtime, false, [CountingExtension::class]),
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
        $list = ExtensionList::merge([], ['noting' => [NotingExtension::class]]);

        $this->assertEquals([new ExtensionEntry('noting', NotingExtension::class, ExtensionOrigin::Runtime, false, [NotingExtension::class])], $list->entries);
        $this->assertSame(['noting' => NotingExtension::class], $list->enabled());
    }

    #[Test]
    public function it_keeps_the_valid_entries_beside_the_refused_ones(): void
    {
        $list = ExtensionList::merge(
            ['counting' => CountingExtension::class, 'Bad' => NotingExtension::class, 0 => InjectedExtension::class, 'archive' => 1, 'tenant' => false],
            ['noting' => [NotingExtension::class]],
        );

        $this->assertEquals([
            new ExtensionEntry('noting', NotingExtension::class, ExtensionOrigin::Runtime, false, [NotingExtension::class]),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Config, false),
            new ExtensionEntry('tenant', null, ExtensionOrigin::Config, true),
        ], $list->entries);
        $this->assertEquals([
            InvalidExtensionException::invalidName('Bad'),
            InvalidExtensionException::missingName(InjectedExtension::class),
            InvalidExtensionException::notAClass('archive', 'int'),
        ], $list->refused);
    }

    #[Test]
    public function it_refuses_a_name_that_two_classes_share_without_config(): void
    {
        $list = ExtensionList::merge([], ['acl' => [NotingExtension::class, CountingExtension::class], 'counting' => [CountingExtension::class]]);

        $this->assertEquals([
            new ExtensionEntry('acl', null, ExtensionOrigin::Runtime, false, [NotingExtension::class, CountingExtension::class]),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Runtime, false, [CountingExtension::class]),
        ], $list->entries);
        $this->assertEquals([InvalidExtensionException::sharedName('acl', [NotingExtension::class, CountingExtension::class])], $list->refused);
        $this->assertSame(
            'The [acl] extension name is registered by several packages, as '.NotingExtension::class.' and '.CountingExtension::class.'.'
            ." Settle it in the [rest.extensions] config: 'acl' => ".NotingExtension::class."::class keeps one, 'acl' => false keeps none, and the other class can take another name there.",
            $list->refused[0]->getMessage(),
        );

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [NotingExtension::class, CountingExtension::class]));

        $list->enabled();
    }

    #[Test]
    public function it_lets_false_settle_a_shared_name(): void
    {
        $list = ExtensionList::merge(['acl' => false], ['acl' => [NotingExtension::class, CountingExtension::class]]);

        $this->assertEquals([new ExtensionEntry('acl', null, ExtensionOrigin::Runtime, true, [NotingExtension::class, CountingExtension::class])], $list->entries);
        $this->assertSame([], $list->refused);
        $this->assertSame([], $list->enabled());
    }

    #[Test]
    public function it_lets_a_config_class_settle_a_shared_name(): void
    {
        $list = ExtensionList::merge(
            ['acl' => BothKindsExtension::class, 'counting' => CountingExtension::class],
            ['acl' => [NotingExtension::class, CountingExtension::class]],
        );

        $this->assertEquals([
            new ExtensionEntry('acl', BothKindsExtension::class, ExtensionOrigin::Config, false, [NotingExtension::class, CountingExtension::class]),
            new ExtensionEntry('counting', CountingExtension::class, ExtensionOrigin::Config, false),
        ], $list->entries);
        $this->assertSame([], $list->refused);
        $this->assertSame(['acl' => BothKindsExtension::class, 'counting' => CountingExtension::class], $list->enabled());
    }

    #[Test]
    public function it_refuses_the_enabled_list_with_the_first_refused_entry(): void
    {
        $list = ExtensionList::merge(['Bad' => NotingExtension::class, 'Worse' => CountingExtension::class], []);

        $this->expectExceptionObject(InvalidExtensionException::invalidName('Bad'));

        $list->enabled();
    }

    #[Test]
    public function it_refuses_a_config_list_without_names(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::missingName(NotingExtension::class));

        ExtensionList::merge([NotingExtension::class], [])->enabled();
    }

    #[Test]
    public function it_names_the_index_of_a_config_entry_without_a_name_or_class(): void
    {
        $list = ExtensionList::merge([false, 1], []);

        $this->assertEquals([
            InvalidExtensionException::unnamedEntry(0, false),
            InvalidExtensionException::unnamedEntry(1, 1),
        ], $list->refused);
        $this->assertStringStartsWith('The extension entry at index [0] is [bool] and has no name.', $list->refused[0]->getMessage());
    }

    #[Test]
    public function it_refuses_an_invalid_config_name(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::invalidName('Acl'));

        ExtensionList::merge(['Acl' => NotingExtension::class], [])->enabled();
    }

    #[Test]
    public function it_refuses_a_config_name_with_two_or_a_trailing_underscore(): void
    {
        $list = ExtensionList::merge(['acl__x' => NotingExtension::class, 'acl_' => CountingExtension::class, 'row_lock' => false], []);

        $this->assertEquals([new ExtensionEntry('row_lock', null, ExtensionOrigin::Config, true)], $list->entries);
        $this->assertEquals([InvalidExtensionException::invalidName('acl__x'), InvalidExtensionException::invalidName('acl_')], $list->refused);
    }

    #[Test]
    public function it_refuses_a_config_name_with_a_trailing_newline(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::invalidName("acl\n"));

        ExtensionList::merge(["acl\n" => NotingExtension::class], [])->enabled();
    }

    #[Test]
    #[DataProvider('neitherClassNamesNorFalse')]
    public function it_refuses_a_config_value_that_is_neither_a_class_name_nor_false(mixed $value, string $type): void
    {
        $this->expectExceptionObject(InvalidExtensionException::notAClass('acl', $type));

        ExtensionList::merge(['acl' => $value], [])->enabled();
    }

    #[Test]
    public function it_asks_for_the_class_name_of_an_instance_in_the_config(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::instanceGiven('acl', NotingExtension::class));

        ExtensionList::merge(['acl' => new NotingExtension], [])->enabled();
    }

    /** @return array<string, array{mixed, string}> */
    public static function neitherClassNamesNorFalse(): array
    {
        return [
            'true' => [true, 'bool'],
            'an integer' => [1, 'int'],
            'an array' => [[], 'array'],
            'null' => [null, 'null'],
        ];
    }
}
