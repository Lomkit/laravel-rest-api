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

        $this->assertSame(['noting' => NotingExtension::class, 'counting' => CountingExtension::class], $extensions->all());
    }

    #[Test]
    public function it_registers_a_map_of_classes_by_name(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('first', CountingExtension::class);

        $extensions->registerAll(['noting' => NotingExtension::class, 'counting' => CountingExtension::class]);

        $this->assertSame([
            'first' => CountingExtension::class,
            'noting' => NotingExtension::class,
            'counting' => CountingExtension::class,
        ], $extensions->all());
    }

    #[Test]
    public function it_refuses_a_second_class_under_a_registered_name(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('noting', NotingExtension::class);

        $this->expectExceptionObject(InvalidExtensionException::duplicateName('noting', NotingExtension::class, CountingExtension::class));

        $extensions->register('noting', CountingExtension::class);
    }

    #[Test]
    public function it_refuses_a_name_registered_twice_in_one_map(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register('noting', NotingExtension::class);

        $this->expectExceptionObject(InvalidExtensionException::duplicateName('noting', NotingExtension::class, CountingExtension::class));

        $extensions->registerAll(['noting' => CountingExtension::class]);
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
        $this->assertSame([$name => NotingExtension::class], $extensions->all());
    }

    #[Test]
    public function it_refuses_a_map_entry_without_a_name(): void
    {
        $this->expectExceptionObject(InvalidExtensionException::missingName(NotingExtension::class));

        (new RuntimeExtensions)->registerAll([NotingExtension::class]);
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

        $this->assertSame(['noting' => NotingExtension::class], $extensions->all());
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
            '33 characters' => [str_repeat('a', 33)],
        ];
    }

    /** @return array<string, array{mixed, string}> */
    public static function notClassNames(): array
    {
        return [
            'an instance' => [new NotingExtension, NotingExtension::class],
            'false' => [false, 'bool'],
            'null' => [null, 'null'],
        ];
    }
}
