<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Lomkit\Rest\Http\Resource;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

final readonly class ResourceIndex
{
    public function __construct(private string $directory, private string $namespace) {}

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

            if (is_subclass_of($class, Resource::class) && ! (new ReflectionClass($class))->isAbstract()) {
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
}
