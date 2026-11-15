<?php

namespace Lomkit\Rest\Tests\Unit\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BothKindsExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotAnExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotesResources;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\RenamableExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeExtensionsTest extends TestCase
{
    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    private const string SOURCE = 'Rest::registerExtensions()';

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

        $extensions->register(NotingExtension::class);
        $extensions->register(CountingExtension::class);

        $this->assertSame([NotingExtension::class, CountingExtension::class], $extensions->all());
    }

    #[Test]
    public function it_registers_a_list_of_classes(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register(CountingExtension::class);

        $extensions->registerAll([NotingExtension::class, BothKindsExtension::class]);

        $this->assertSame([CountingExtension::class, NotingExtension::class, BothKindsExtension::class], $extensions->all());
    }

    #[Test]
    public function it_counts_the_same_class_once(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register(NotingExtension::class);

        $extensions->register(NotingExtension::class);
        $extensions->registerAll([NotingExtension::class, NotingExtension::class]);

        $this->assertSame([NotingExtension::class], $extensions->all());
    }

    #[Test]
    public function it_reads_the_name_from_the_class_at_registration(): void
    {
        $extensions = new RuntimeExtensions;

        $extensions->register(RenamableExtension::class);

        $this->assertSame([RenamableExtension::class], ExtensionLog::$named);
        $this->assertSame([RenamableExtension::class], $extensions->all());
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_refuses_a_class_whose_name_breaks_the_name_rule(string $name): void
    {
        $extensions = new RuntimeExtensions;
        RenamableExtension::$given = $name;

        try {
            $extensions->register(RenamableExtension::class);
            $this->fail("The name [{$name}] was accepted.");
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::invalidName(RenamableExtension::class, $name), $exception);
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    public function it_accepts_a_name_of_32_characters_with_digits_and_underscores(): void
    {
        $extensions = new RuntimeExtensions;
        RenamableExtension::$given = str_repeat('a', 30).'_1';

        $extensions->register(RenamableExtension::class);

        $this->assertSame(32, strlen(RenamableExtension::$given));
        $this->assertSame([RenamableExtension::class], $extensions->all());
    }

    #[Test]
    public function it_accepts_words_joined_by_single_underscores(): void
    {
        $extensions = new RuntimeExtensions;

        foreach (['row_lock_2', 'a1_b2'] as $name) {
            RenamableExtension::$given = $name;
            $extensions->registerAll([RenamableExtension::class]);
        }

        $this->assertSame([RenamableExtension::class], $extensions->all());
    }

    #[Test]
    public function it_explains_the_name_rule_and_names_the_class(): void
    {
        $this->assertSame(
            RenamableExtension::class.'::name() returns [acl__x], which is not a valid extension name. A name has 1 to 32 lowercase letters or digits, starts with a letter and joins words with single underscores, such as [acl] or [row_lock]: it prefixes the keys that the extension adds, and two underscores separate it from them.'
            ." Return a name such as 'acl' from ".RenamableExtension::class.'::name().',
            InvalidExtensionException::invalidName(RenamableExtension::class, 'acl__x')->getMessage(),
        );
    }

    /** @param class-string $class */
    #[Test]
    #[DataProvider('uncheckedClasses')]
    public function it_checks_the_class_before_it_reads_the_name(string $class, InvalidExtensionException $refusal): void
    {
        $extensions = new RuntimeExtensions;

        foreach ([fn () => $extensions->register($class), fn () => $extensions->registerAll([NotingExtension::class, $class])] as $registration) {
            try {
                $registration();
                $this->fail("The class [{$class}] was accepted.");
            } catch (InvalidExtensionException $exception) {
                $this->assertEquals($refusal, $exception);
            }
        }

        $this->assertSame([], $extensions->all());
    }

    #[Test]
    public function it_explains_each_class_refusal(): void
    {
        $this->assertSame('The extension ['.self::MISSING.'] is not a class. Give the name of a class that extends '.Extension::class.'.', InvalidExtensionException::notAClass(self::MISSING)->getMessage());
        $this->assertSame('The extension '.NotAnExtension::class.' does not extend '.Extension::class.'. Extend it.', InvalidExtensionException::notAnExtension(NotAnExtension::class)->getMessage());
        $this->assertSame('The extension '.AbstractNotingExtension::class.' is abstract or cannot be instantiated. Register a concrete class.', InvalidExtensionException::notInstantiable(AbstractNotingExtension::class)->getMessage());
    }

    #[Test]
    public function it_asks_for_the_list_form_instead_of_an_instance(): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll([new NotingExtension]);
            $this->fail('The instance was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::instanceGiven(self::SOURCE, NotingExtension::class), $exception);
            $this->assertSame(
                'Rest::registerExtensions() holds an instance of '.NotingExtension::class.'. Give its class name in the list, such as ['.NotingExtension::class.'::class]: the container builds each extension once per context, and an instance would outlive the context.',
                $exception->getMessage(),
            );
            $this->assertSame([], $extensions->all());
        }
    }

    #[Test]
    #[DataProvider('notClassNames')]
    public function it_asks_for_the_list_form_instead_of_a_value_that_is_not_a_class_name(mixed $value, string $type): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll([NotingExtension::class, $value]);
            $this->fail('The value was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::notAClassName(self::SOURCE, $type), $exception);
            $this->assertSame(
                "Rest::registerExtensions() holds [{$type}], which is not a class name. Give a list of extension class names, such as [AccessControlExtension::class].",
                $exception->getMessage(),
            );
            $this->assertSame([], $extensions->all());
        }
    }

    /** @param array<array-key, mixed> $classes */
    #[Test]
    #[DataProvider('notLists')]
    public function it_asks_for_the_list_form_instead_of_an_array_that_is_not_a_list(array $classes): void
    {
        $extensions = new RuntimeExtensions;

        try {
            $extensions->registerAll($classes);
            $this->fail('The array was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::notAList(self::SOURCE), $exception);
            $this->assertSame(
                'Rest::registerExtensions() takes a list of extension class names, such as [AccessControlExtension::class]: each extension class declares its own name.',
                $exception->getMessage(),
            );
            $this->assertSame([], $extensions->all());
            $this->assertSame([], ExtensionLog::$named);
        }
    }

    #[Test]
    public function it_refuses_a_registration_once_frozen(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->register(NotingExtension::class);
        $extensions->freeze();

        try {
            $extensions->register(RenamableExtension::class);
            $this->fail('The registration was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::lateRegistration(RenamableExtension::class), $exception);
        }

        try {
            $extensions->registerAll([CountingExtension::class]);
            $this->fail('The registration was accepted.');
        } catch (InvalidExtensionException $exception) {
            $this->assertEquals(InvalidExtensionException::lateRegistration(CountingExtension::class), $exception);
        }

        $this->assertSame([NotingExtension::class], $extensions->all());
        $this->assertSame([], ExtensionLog::$named);
    }

    #[Test]
    public function it_names_the_class_in_a_late_registration(): void
    {
        $extensions = new RuntimeExtensions;
        $extensions->freeze();

        $this->expectExceptionMessage(
            'The extension '.NotingExtension::class.' is registered after the application booted, or after the package built its first context or registered its first Rest::resource() route: the runtime extension list no longer changes then.'
            .' Register extensions from the register() method of a service provider, which runs before the routes in any provider order.'
            .' Register from boot() only before every provider that declares Rest::resource() routes in its own boot().',
        );

        $extensions->register(NotingExtension::class);
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
            'two underscores' => ['acl__x'],
            'a trailing underscore' => ['acl_'],
            'only underscores after a letter' => ['a__'],
        ];
    }

    /** @return array<string, array{string, InvalidExtensionException}> */
    public static function uncheckedClasses(): array
    {
        return [
            'a missing class' => [self::MISSING, InvalidExtensionException::notAClass(self::MISSING)],
            'a kind interface' => [NotesResources::class, InvalidExtensionException::notAClass(NotesResources::class)],
            'a class that is not an extension' => [NotAnExtension::class, InvalidExtensionException::notAnExtension(NotAnExtension::class)],
            'the extension base class' => [Extension::class, InvalidExtensionException::notAnExtension(Extension::class)],
            'an abstract class without name()' => [AbstractNotingExtension::class, InvalidExtensionException::notInstantiable(AbstractNotingExtension::class)],
        ];
    }

    /** @return array<string, array{mixed, string}> */
    public static function notClassNames(): array
    {
        return [
            'false' => [false, 'bool'],
            'null' => [null, 'null'],
            'an integer' => [1, 'int'],
            'a nested list' => [[NotingExtension::class], 'array'],
        ];
    }

    /** @return array<string, array{array<array-key, mixed>}> */
    public static function notLists(): array
    {
        return [
            'a map by name' => [['acl' => 'Vendor\Acl\AccessControl']],
            'a list with a gap' => [[1 => NotingExtension::class]],
        ];
    }
}
