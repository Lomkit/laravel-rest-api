<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Lomkit\Rest\Tests\Feature\Text\Engine\Algolia\AlgoliaTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Meilisearch\MeilisearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer\TurbopufferTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Typesense\TypesenseTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use SplFileInfo;

class EngineArchitectureTest extends TestCase
{
    private const array ENGINE_GROUPS = [
        AlgoliaTestCase::class => 'algolia',
        MeilisearchTestCase::class => 'meilisearch',
        TurbopufferTestCase::class => 'turbopuffer',
        TypesenseTestCase::class => 'typesense',
    ];

    #[Test]
    public function it_names_no_class_of_a_search_engine_client(): void
    {
        $root = dirname(__DIR__, 3);
        $files = [];

        foreach (self::files("{$root}/src", '.php') as $path) {
            if (preg_match('/(Meilisearch|Typesense|Algolia(Search)?)\\\\/', (string) file_get_contents($path)) === 1) {
                $files[] = substr($path, strlen($root) + 1);
            }
        }

        $this->assertSame([], $files);
    }

    #[Test]
    public function it_names_scout_classes_only_in_the_engine_namespace(): void
    {
        $root = dirname(__DIR__, 3);
        $files = [];

        foreach (self::files("{$root}/src", '.php') as $path) {
            if (! str_starts_with($path, "{$root}/src/Text/Engine/") && self::namesScout((string) file_get_contents($path))) {
                $files[] = substr($path, strlen($root) + 1);
            }
        }

        $this->assertSame([], $files);
    }

    #[Test]
    public function it_groups_every_engine_test_with_scout_and_its_engine(): void
    {
        $expected = [];
        $actual = [];

        foreach (self::suiteClasses() as $class) {
            $reflection = new ReflectionClass($class);

            foreach (self::ENGINE_GROUPS as $base => $engine) {
                if ($reflection->isSubclassOf($base) && ! $reflection->isAbstract()) {
                    $expected[$class] = ['scout', $engine];
                    $actual[$class] = array_map(fn (ReflectionAttribute $group): string => $group->newInstance()->name(), $reflection->getAttributes(Group::class));
                }
            }
        }

        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
        $this->assertEqualsCanonicalizing(array_values(self::ENGINE_GROUPS), array_values(array_unique(array_column($expected, 1))));
    }

    #[Test]
    public function it_names_no_backend_in_the_search_parser(): void
    {
        $root = dirname(__DIR__, 3);
        $sources = self::files("{$root}/src", '.php');
        $grammars = ["{$root}/src/Validation/Search/SearchGrammar.php", "{$root}/src/Validation/Search/SqlSearchGrammar.php"];
        $parser = [
            ...array_diff(self::files("{$root}/src/Validation/Search", '.php'), $grammars),
            ...self::files("{$root}/src/Validation/Operate", '.php'),
            ...self::files("{$root}/src/Validation/Mutate", '.php'),
            ...self::files("{$root}/src/Mutations", '.php'),
            "{$root}/src/Validation/InputSet.php",
            "{$root}/src/Validation/PayloadReader.php",
            "{$root}/src/Validation/ParsedRules.php",
        ];

        $this->assertSame([], self::matching($root, $parser, '/engine|TextBackend|ResolvedText|backend\(|context\(\)->text\(/i'));
        $this->assertSame([], self::matching($root, $grammars, '/engine/i'));
        $this->assertSame(['src/Text/Engine/EngineGrammar.php'], self::matching($root, $sources, '/new EngineSearchGrammar\b/'));
        $this->assertSame(
            ['src/Text/Engine/EngineSearchGrammar.php', 'src/Text/ResolvedText.php', 'src/Validation/Search/SqlSearchGrammar.php'],
            self::matching($root, $sources, '/SqlSearchGrammar/'),
        );
        $this->assertSame([], self::matching($root, $sources, '/instanceof [A-Za-z\\\\]*SearchGrammar/'));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function matching(string $root, array $paths, string $pattern): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (preg_match($pattern, (string) file_get_contents($path)) === 1) {
                $files[] = substr($path, strlen($root) + 1);
            }
        }

        return $files;
    }

    private static function namesScout(string $code): bool
    {
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && str_starts_with(ltrim($token[1], '\\').'\\', 'Laravel\\Scout\\')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<class-string> */
    private static function suiteClasses(): array
    {
        $tests = dirname(__DIR__, 2);
        $classes = [];

        foreach (self::files($tests, 'Test.php') as $path) {
            $class = 'Lomkit\\Rest\\Tests\\'.str_replace('/', '\\', substr($path, strlen($tests) + 1, -4));

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** @return list<string> */
    private static function files(string $directory, string $suffix): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getPathname(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
