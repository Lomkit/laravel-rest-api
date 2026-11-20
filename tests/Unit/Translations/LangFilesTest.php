<?php

namespace Lomkit\Rest\Tests\Unit\Translations;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LangFilesTest extends TestCase
{
    private const string LANG = __DIR__.'/../../../lang';

    private const string SOURCE = __DIR__.'/../../../src';

    #[Test]
    public function it_ships_the_english_lines_only(): void
    {
        $this->assertSame(['en'], array_values(array_diff((array) scandir(self::LANG), ['.', '..'])));
    }

    #[Test]
    public function it_ships_every_line_that_the_source_names(): void
    {
        $named = self::named();

        $this->assertNotSame([], $named);
        $this->assertSame([], array_values(array_diff($named, array_keys(self::lines()))));
    }

    #[Test]
    public function it_ships_only_lines_that_the_source_names(): void
    {
        $this->assertSame([], array_values(array_diff(array_keys(self::lines()), self::named())));
    }

    /** @return array<string, string> */
    private static function lines(): array
    {
        $lines = [];

        foreach (glob(self::LANG.'/en/*.php') ?: [] as $path) {
            $lines += Arr::dot([basename($path, '.php') => require $path]);
        }

        return $lines;
    }

    /** @return list<string> */
    private static function named(): array
    {
        $named = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SOURCE)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                preg_match_all("/'rest::([a-z0-9_]+\\.[a-z0-9_]+)'/", (string) file_get_contents($file->getPathname()), $matches);
                $named = [...$named, ...$matches[1]];
            }
        }

        return array_values(array_unique($named));
    }
}
