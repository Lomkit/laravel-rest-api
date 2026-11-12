<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\FieldValues;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldValuesExtensionTest extends TestCase
{
    #[Test]
    public function it_registers_a_shipped_extension_for_phpstan(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode((string) file_get_contents("{$root}/composer.json"), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['extension.neon'], $composer['extra']['phpstan']['includes']);
        $this->assertFileExists("{$root}/extension.neon");
        $this->assertStringNotContainsString('extension.neon', (string) file_get_contents("{$root}/.gitattributes"));
    }

    #[Test]
    public function it_declares_the_field_values_as_object_crates(): void
    {
        $extension = (string) file_get_contents(dirname(__DIR__, 3).'/extension.neon');

        preg_match_all('/^\s+- (\S+)$/m', $extension, $classes);

        $this->assertStringContainsString("universalObjectCratesClasses:\n", $extension);
        $this->assertSame([FieldValues::class], $classes[1]);
        $this->assertTrue(class_exists($classes[1][0]));
    }
}
