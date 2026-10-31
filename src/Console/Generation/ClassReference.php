<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;

final readonly class ClassReference
{
    private const string VALID_NAME = '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

    private function __construct(private string $fqcn) {}

    public static function fromInput(string $input, string $rootNamespace, string $namespace): self
    {
        $name = str_replace('/', '\\', ltrim($input, '\\/'));

        if (preg_match(self::VALID_NAME, $name) !== 1) {
            throw InvalidClassReferenceException::invalidName($input);
        }

        if (str_starts_with($name, $rootNamespace) || (str_contains($name, '\\') && class_exists($name))) {
            return new self($name);
        }

        return new self($namespace.$name);
    }

    public static function guessModel(string $resource, string $namespace): ?self
    {
        return self::guess($resource, 'Resource', fn (string $model): string => $model, $namespace);
    }

    public static function guessPolicyModel(string $policy, string $namespace): ?self
    {
        return self::guess($policy, 'Policy', fn (string $model): string => $model, $namespace);
    }

    public static function guessResource(string $controller, string $namespace): ?self
    {
        return self::guess($controller, 'Controller', fn (string $name): string => Str::singular($name).'Resource', $namespace);
    }

    public function fqcn(): string
    {
        return $this->fqcn;
    }

    public function exists(): bool
    {
        return class_exists($this->fqcn);
    }

    /** @param  callable(string): string  $transform */
    private static function guess(string $class, string $suffix, callable $transform, string $namespace): ?self
    {
        $basename = Str::replaceEnd($suffix, '', class_basename($class));

        if ($basename === '') {
            return null;
        }

        $prefix = Str::contains($class, '\\') ? Str::beforeLast($class, '\\').'\\' : '';
        $reference = new self($namespace.$prefix.$transform($basename));

        return $reference->exists() ? $reference : null;
    }
}
