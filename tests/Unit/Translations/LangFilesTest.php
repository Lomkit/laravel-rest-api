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
    public function it_ships_the_same_lines_in_english_and_in_french(): void
    {
        $english = self::lines('en');

        $this->assertNotSame([], $english);
        $this->assertSame(array_keys($english), array_keys(self::lines('fr')));
    }

    #[Test]
    public function it_gives_each_french_line_the_placeholders_of_its_english_line(): void
    {
        $french = self::lines('fr');

        foreach (self::lines('en') as $key => $english) {
            $this->assertSame(self::placeholders($english), self::placeholders($french[$key] ?? ''), $key);
        }
    }

    #[Test]
    public function it_ships_every_line_that_the_source_names(): void
    {
        $english = self::lines('en');
        $named = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SOURCE)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                preg_match_all("/'rest::([a-z_]+\\.[a-z_]+)'/", (string) file_get_contents($file->getPathname()), $matches);
                $named = [...$named, ...$matches[1]];
            }
        }

        $this->assertNotSame([], $named);
        $this->assertSame([], array_values(array_diff(array_unique($named), array_keys($english))));
    }

    /** @return array<string, string> */
    private static function lines(string $locale): array
    {
        $lines = [];

        foreach (glob(self::LANG.'/'.$locale.'/*.php') ?: [] as $path) {
            $lines += Arr::dot([basename($path, '.php') => require $path]);
        }

        ksort($lines);

        return $lines;
    }

    /** @return list<string> */
    private static function placeholders(string $line): array
    {
        preg_match_all('/:[A-Za-z_]+/', $line, $matches);
        $placeholders = array_values(array_unique($matches[0]));
        sort($placeholders);

        return $placeholders;
    }
}
