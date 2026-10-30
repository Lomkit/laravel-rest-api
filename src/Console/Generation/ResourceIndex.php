<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

final readonly class ResourceIndex
{
    /** @param  array<string, string>  $planned */
    public function __construct(private string $directory, private string $namespace, private array $planned = []) {}

    public function withPlanned(string $resource, string $model): self
    {
        return new self($this->directory, $this->namespace, [...$this->planned, $resource => $model]);
    }

    /** @return list<class-string<Resource>> */
    public function classes(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->in($this->directory);

        $classes = [];

        foreach ($files as $file) {
            $class = $this->namespace.str_replace('/', '\\', Str::beforeLast($file->getRelativePathname(), '.php'));

            if (ClassFile::read($file->getPathname())->declaresOnlyTheChildClass($class) && is_subclass_of($class, Resource::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map(fn (string $class): string => Str::after($class, $this->namespace), $this->classes());
    }

    /** @return array<string, string> */
    public function byModel(): array
    {
        $resources = [];

        foreach ($this->planned as $resource => $model) {
            $resources[$model] ??= $resource;
        }

        foreach ($this->classes() as $class) {
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
