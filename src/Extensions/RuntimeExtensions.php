<?php

namespace Lomkit\Rest\Extensions;

use Illuminate\Container\Attributes\Singleton;
use Lomkit\Rest\Exceptions\InvalidExtensionException;

/** @internal */
#[Singleton]
final class RuntimeExtensions
{
    /** @var array<string, string> */
    private array $classes = [];

    private bool $frozen = false;

    public function register(string $name, string $class): void
    {
        $this->ensureAccepted($name, $class);

        $this->classes[$name] = $class;
    }

    /** @param array<array-key, mixed> $classes */
    public function registerAll(array $classes): void
    {
        $accepted = [];

        foreach ($classes as $name => $class) {
            if (! is_string($name)) {
                throw is_string($class) ? InvalidExtensionException::missingName($class) : InvalidExtensionException::unnamedEntry($name, $class);
            }

            if (is_object($class)) {
                throw InvalidExtensionException::instanceGiven($name, $class::class);
            }

            if (! is_string($class)) {
                throw InvalidExtensionException::notAClass($name, get_debug_type($class));
            }

            $this->ensureAccepted($name, $class);
            $accepted[$name] = $class;
        }

        $this->classes = [...$this->classes, ...$accepted];
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->classes;
    }

    private function ensureAccepted(string $name, string $class): void
    {
        if ($this->frozen) {
            throw InvalidExtensionException::lateRegistration($name, $class);
        }

        ExtensionName::ensureValid($name);

        if (isset($this->classes[$name])) {
            throw InvalidExtensionException::duplicateName($name, $this->classes[$name], $class);
        }
    }
}
