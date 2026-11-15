<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeExtensionsTest extends TestCase
{
    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        ExtensionLog::reset();
    }

    #[Test]
    public function it_keeps_the_registrations_in_their_order(): void
    {
        $extensions = new RuntimeExtensions;

        $extensions->register('noting', NotingExtension::class);
        $extensions->register('counting', CountingExtension::class);

        $this->assertSame(['noting' => [NotingExtension::class], 'counting' => [CountingExtension::class]], $extensions->all());
    }

    #[Test]
    public function it_registers_a_map_of_classes_by_name(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('first', CountingExtension::class);

        $extensions->registerAll(['noting' => NotingExtension::class, 'counting' => CountingExtension::class]);

        $this->assertSame([
            'first' => [CountingExtension::class],
            'noting' => [NotingExtension::class],
            'counting' => [CountingExtension::class],
        ], $extensions->all());
    }

    #[Test]
    public function it_records_a_second_class_under_a_registered_name(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('noting', NotingExtension::class);

        $extensions->register('noting', CountingExtension::class);

        $this->assertSame(['noting' => [NotingExtension::class, CountingExtension::class]], $extensions->all());
    }

    #[Test]
    public function it_accepts_the_same_class_twice_under_one_name_silently(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('noting', NotingExtension::class);

        $extensions->register('noting', NotingExtension::class);
        $extensions->registerAll(['noting' => NotingExtension::class]);

        $this->assertSame(['noting' => [NotingExtension::class]], $extensions->all());
    }

    #[Test]
    public function it_records_a_map_entry_under_a_registered_name(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('noting', NotingExtension::class);

        $extensions->registerAll(['noting' => CountingExtension::class, 'counting' => CountingExtension::class]);

        $this->assertSame([
            'noting' => [NotingExtension::class, CountingExtension::class],
            'counting' => [CountingExtension::class],
        ], $extensions->all());
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_refuses_a_name_that_breaks_the_name_rule(string $name): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->register($name, NotingExtension::class);
            $this->fail("The name [{$name}] was accepted.");
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::invalidName($name), $exception);
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    public function it_accepts_a_name_of_32_characters_with_digits_and_underscores(): void
    {
        $extensions = new RuntimeExtensions;
        $name = str_repeat('a', 30).'_1';

        $extensions->register($name, NotingExtension::class);

        $this->assertSame(32, strlen($name));
        $this->assertSame([$name => [NotingExtension::class]], $extensions->all());
    }

    #[Test]
    public function it_refuses_a_map_entry_without_a_name(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::missingName(NotingExtension::class));

        (new RuntimeExtensions)->registerAll([NotingExtension::class]);
    }

    #[Test]
    public function it_names_the_index_of_a_map_entry_without_a_name_or_class(): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll([1]);
            $this->fail('The entry was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::unnamedEntry(0, 1), $exception);
            $this->assertSame(
                "The extension entry at index [0] is [int] and has no name. Key the class name of each extension by its name, such as 'acl' => AccessControlExtension::class, in the [rest.extensions] config or in Rest::registerExtensions(); only the config accepts 'acl' => false, to disable an extension.",
                $exception->getMessage(),
            );
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    public function it_asks_for_a_name_and_the_class_name_of_an_unnamed_instance(): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll([new NotingExtension]);
            $this->fail('The instance was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::unnamedEntry(0, new NotingExtension), $exception);
            $this->assertSame(
                'The extension entry at index [0] is an instance of '.NotingExtension::class." and has no name. Key its class name by the extension name, such as 'acl' => ".NotingExtension::class.'::class, in the [rest.extensions] config or in Rest::registerExtensions().',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    #[DataProvider('notClassNames')]
    public function it_refuses_a_map_value_that_is_not_a_class_name(mixed $value, string $type): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll(['noting' => $value]);
            $this->fail('The value was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::notAClass('noting', $type), $exception);
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    public function it_asks_for_the_class_name_of_an_instance(): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll(['noting' => new NotingExtension]);
            $this->fail('The instance was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::instanceGiven('noting', NotingExtension::class), $exception);
            $this->assertSame(
                'The [noting] extension is an instance of '.NotingExtension::class.'. Register its class name, '.NotingExtension::class.'::class: the container builds each extension once per context, and an instance would outlive the context.',
                $exception->getMessage(),
            );
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    public function it_keeps_nothing_from_a_map_with_one_refused_entry(): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll(['noting' => NotingExtension::class, 'Counting' => CountingExtension::class]);
            $this->fail('The map was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::invalidName('Counting'), $exception);
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    public function it_refuses_a_registration_once_frozen(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('noting', NotingExtension::class);
        $extensions->freeze();

        try {
            $extensions->register('counting', CountingExtension::class);
            $this->fail('The registration was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::lateRegistration('counting', CountingExtension::class), $exception);
        }

        try {
            $extensions->registerAll(['counting' => CountingExtension::class]);
            $this->fail('The registration was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::lateRegistration('counting', CountingExtension::class), $exception);
        }

        $this->assertSame(['noting' => [NotingExtension::class]], $extensions->all());
    }

    #[Test]
    public function it_advises_register_over_boot_in_a_late_registration(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->freeze();

        $this->expectExceptionMessage(
            'The [noting] extension '.NotingExtension::class.' is registered after the application booted, after the package built its first context or registered its first Rest::resource() route: the runtime extension list no longer changes then.'
            .' Register extensions from the register() method of a service provider, which runs before the routes in any provider order.'
            .' Register from boot() only before every provider that declares Rest::resource() routes in its own boot().',
        );

        $extensions->register('noting', NotingExtension::class);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'an empty name' => [''],
            'an uppercase letter' => ['Acl'],
            'a leading digit' => ['1acl'],
            'a leading underscore' => ['_acl'],
            'a dash' => ['acl-x'],
            'a space' => ['acl x'],
            'a dot' => ['acl.x'],
            'a trailing newline' => ["acl\n"],
            '33 characters' => [str_repeat('a', 33)],
        ];
    }

    /** @return array<string, array{mixed, string}> */
    public static function notClassNames(): array
    {
        return [
            'false' => [false, 'bool'],
            'null' => [null, 'null'],
        ];
    }
}
