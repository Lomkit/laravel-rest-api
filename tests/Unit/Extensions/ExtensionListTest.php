<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\ExtensionOrigin;
use Lomkit\Rest\Extensions\NamedExtensions;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\AppAclExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BadlyNamedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\InjectedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotAnExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\RenamableExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\RivalAclExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtensionListTest extends TestCase
{
    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    public function it_lists_the_runtime_classes_then_the_config_classes_by_the_name_of_each_class(): void
    {
        $named = ExtensionList::of([BothKindsExtension::class, InjectedExtension::class], [], [NotingExtension::class, CountingExtension::class])->named();

        $this->assertEquals([
            new ExtensionEntry(NotingExtension::class, ExtensionOrigin::Runtime, false, 'noting'),
            new ExtensionEntry(CountingExtension::class, ExtensionOrigin::Runtime, false, 'counting'),
            new ExtensionEntry(BothKindsExtension::class, ExtensionOrigin::Config, false, 'both'),
            new ExtensionEntry(InjectedExtension::class, ExtensionOrigin::Config, false, 'injected'),
        ], $named->entries);
        $this->assertEnabled(['noting', 'counting', 'both', 'injected'], [NotingExtension::class, CountingExtension::class, BothKindsExtension::class, InjectedExtension::class], $named);
    }

    #[Test]
    public function it_reads_no_name_before_the_names_are_asked(): void
    {
        $list = ExtensionList::of([RenamableExtension::class], [], []);

        $this->assertEquals([new ExtensionEntry(RenamableExtension::class, ExtensionOrigin::Config)], $list->entries);
        $this->assertSame([], ExtensionLog::$named);

        $this->assertEnabled(['renamable'], [RenamableExtension::class], $list->named());
        $this->assertSame([RenamableExtension::class], ExtensionLog::$named);
    }

    #[Test]
    public function it_counts_a_class_in_both_sources_once_with_the_config_origin(): void
    {
        $named = ExtensionList::of([NotingExtension::class, NotingExtension::class], [], [NotingExtension::class, CountingExtension::class])->named();

        $this->assertEquals([
            new ExtensionEntry(NotingExtension::class, ExtensionOrigin::Config, false, 'noting'),
            new ExtensionEntry(CountingExtension::class, ExtensionOrigin::Runtime, false, 'counting'),
        ], $named->entries);
        $this->assertSame([], $named->refused);
    }

    #[Test]
    public function it_disables_a_runtime_class_and_a_config_class(): void
    {
        $list = ExtensionList::of([CountingExtension::class], [NotingExtension::class, CountingExtension::class], [NotingExtension::class, BothKindsExtension::class]);

        $this->assertEquals([
            new ExtensionEntry(NotingExtension::class, ExtensionOrigin::Runtime, true),
            new ExtensionEntry(BothKindsExtension::class, ExtensionOrigin::Runtime, false, 'both'),
            new ExtensionEntry(CountingExtension::class, ExtensionOrigin::Config, true),
        ], $list->named()->entries);
        $this->assertEnabled(['both'], [BothKindsExtension::class], $list->named());
        $this->assertSame([], $list->idle);
    }

    #[Test]
    public function it_never_reads_the_name_of_a_disabled_class(): void
    {
        RenamableExtension::$given = 'Not a name';

        $named = ExtensionList::of([RenamableExtension::class, AclExtension::class, RivalAclExtension::class], [RenamableExtension::class, RivalAclExtension::class], [])->named();

        $this->assertSame([], ExtensionLog::$named);
        $this->assertSame([], $named->refused);
        $this->assertEnabled(['acl'], [AclExtension::class], $named);
    }

    #[Test]
    public function it_keeps_apart_a_disabled_class_that_disables_nothing(): void
    {
        $list = ExtensionList::of([], [AclExtension::class, self::MISSING, AclExtension::class], [NotingExtension::class]);

        $this->assertSame([AclExtension::class, self::MISSING], $list->idle);
        $this->assertEquals([new ExtensionEntry(NotingExtension::class, ExtensionOrigin::Runtime)], $list->entries);
        $this->assertSame([], $list->refused);
    }

    #[Test]
    public function it_does_not_disable_a_subclass_by_the_entry_of_its_parent(): void
    {
        $list = ExtensionList::of([], [AclExtension::class], [AppAclExtension::class]);

        $this->assertEnabled(['acl'], [AppAclExtension::class], $list->named());
        $this->assertSame([AclExtension::class], $list->idle);
    }

    #[Test]
    public function it_replaces_a_package_class_with_its_subclass_and_a_disabled_entry(): void
    {
        $list = ExtensionList::of([AppAclExtension::class], [AclExtension::class], [AclExtension::class]);

        $this->assertEquals([
            new ExtensionEntry(AclExtension::class, ExtensionOrigin::Runtime, true),
            new ExtensionEntry(AppAclExtension::class, ExtensionOrigin::Config, false, 'acl'),
        ], $list->named()->entries);
        $this->assertEnabled(['acl'], [AppAclExtension::class], $list->named());
        $this->assertSame([], $list->idle);
    }

    #[Test]
    public function it_refuses_a_subclass_beside_its_parent_without_the_disabled_entry(): void
    {
        $named = ExtensionList::of([AppAclExtension::class], [], [AclExtension::class])->named();

        $this->assertEquals([InvalidExtensionException::sharedName('acl', [AclExtension::class, AppAclExtension::class])], $named->refused);

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [AclExtension::class, AppAclExtension::class]));

        $named->enabled();
    }

    #[Test]
    public function it_refuses_two_classes_with_one_name_and_gives_both_fixes(): void
    {
        $named = ExtensionList::of([RivalAclExtension::class], [], [AclExtension::class, CountingExtension::class])->named();

        $this->assertEquals([
            new ExtensionEntry(AclExtension::class, ExtensionOrigin::Runtime, false, 'acl'),
            new ExtensionEntry(CountingExtension::class, ExtensionOrigin::Runtime, false, 'counting'),
            new ExtensionEntry(RivalAclExtension::class, ExtensionOrigin::Config, false, 'acl'),
        ], $named->entries);
        $this->assertSame(['acl'], $named->shared);
        $this->assertEquals([InvalidExtensionException::sharedName('acl', [AclExtension::class, RivalAclExtension::class])], $named->refused);
        $this->assertSame(
            'The extensions '.AclExtension::class.' and '.RivalAclExtension::class.' share the name [acl].'
            .' Add one of them to the [rest.disabled_extensions] config, or extend one of them and override name() to give it another name.',
            $named->refused[0]->getMessage(),
        );

        $this->expectExceptionObject(InvalidExtensionException::sharedName('acl', [AclExtension::class, RivalAclExtension::class]));

        $named->enabled();
    }

    #[Test]
    public function it_keeps_the_valid_entries_beside_the_refused_ones(): void
    {
        $named = ExtensionList::of(
            [CountingExtension::class, self::MISSING, 1, new BothKindsExtension, NotAnExtension::class, AbstractNotingExtension::class, BadlyNamedExtension::class],
            [null],
            [NotingExtension::class],
        )->named();

        $this->assertEquals([
            new ExtensionEntry(NotingExtension::class, ExtensionOrigin::Runtime, false, 'noting'),
            new ExtensionEntry(CountingExtension::class, ExtensionOrigin::Config, false, 'counting'),
        ], $named->entries);
        $this->assertEquals([
            InvalidExtensionException::notAClassName(ExtensionList::CONFIG, 'int'),
            InvalidExtensionException::instanceGiven(ExtensionList::CONFIG, BothKindsExtension::class),
            InvalidExtensionException::notAClassName(ExtensionList::DISABLED, 'null'),
            InvalidExtensionException::notAClass(self::MISSING),
            InvalidExtensionException::notAnExtension(NotAnExtension::class),
            InvalidExtensionException::notInstantiable(AbstractNotingExtension::class),
            InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl'),
        ], $named->refused);
    }

    #[Test]
    public function it_names_the_config_key_of_a_refused_entry(): void
    {
        $this->assertSame(
            'The [rest.extensions] config holds an instance of '.NotingExtension::class.'. Give its class name in the list, such as ['.NotingExtension::class.'::class]: the container builds each extension once per context, and an instance would outlive the context.',
            InvalidExtensionException::instanceGiven(ExtensionList::CONFIG, NotingExtension::class)->getMessage(),
        );
        $this->assertSame(
            'The [rest.disabled_extensions] config holds [bool], which is not a class name. Give a list of extension class names, such as [AccessControlExtension::class].',
            InvalidExtensionException::notAClassName(ExtensionList::DISABLED, 'bool')->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_the_enabled_list_with_the_first_refused_entry(): void
    {
        $named = ExtensionList::of([BadlyNamedExtension::class, self::MISSING], [], [])->named();

        $this->expectExceptionObject(InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl'));

        $named->enabled();
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $classes
     */
    private function assertEnabled(array $names, array $classes, NamedExtensions $named): void
    {
        $this->assertSame($names, array_keys($named->enabled()));
        $this->assertSame($classes, array_values($named->enabled()));
    }
}
