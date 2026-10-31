<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;
use Throwable;

final readonly class ResourceIndex
{
    /**
     * @param  list<class-string<Resource>>  $classes
     * @param  list<string>  $skipped
     * @param  array<string, string>  $planned
     */
    private function __construct(private string $namespace, private array $classes, private array $skipped, private array $planned = []) {}

    public static function scan(string $directory, string $namespace, string $basePath): self
    {
        if (! is_dir($directory)) {
            return new self($namespace, [], []);
        }

        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->in($directory);

        $classes = [];
        $skipped = [];

        foreach ($files as $file) {
            $class = $namespace.str_replace('/', '\\', Str::beforeLast($file->getRelativePathname(), '.php'));

            if (! ClassFile::read($file->getPathname())->declaresOnlyTheChildClass($class)) {
                continue;
            }

            try {
                if (is_subclass_of($class, Resource::class)) {
                    $classes[] = $class;
                }
            } catch (Throwable $error) {
                $skipped[] = "{$class} is skipped: ".LoadFailure::describe($error, $basePath).'.';
            }
        }

        sort($classes);
        sort($skipped);

        return new self($namespace, $classes, $skipped);
    }

    public function withPlanned(string $resource, string $model): self
    {
        return new self($this->namespace, $this->classes, $this->skipped, [...$this->planned, $resource => $model]);
    }

    /** @return list<class-string<Resource>> */
    public function classes(): array
    {
        return $this->classes;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map(fn (string $class): string => Str::after($class, $this->namespace), $this->classes);
    }

    /** @return array<string, string> */
    public function byModel(): array
    {
        $resources = [];

        foreach ($this->planned as $resource => $model) {
            $resources[$model] ??= $resource;
        }

        foreach ($this->classes as $class) {
            $model = self::modelOf($class);

            if ($model !== null) {
                $resources[$model] ??= $class;
            }
        }

        return $resources;
    }

    /** @param  class-string<Resource>  $class */
    private static function modelOf(string $class): ?string
    {
        return (new ReflectionProperty($class, 'model'))->isInitialized() ? $class::modelClass() : null;
    }
}
