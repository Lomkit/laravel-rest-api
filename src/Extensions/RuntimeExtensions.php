<?php

namespace Lomkit\Rest\Extensions;

use Illuminate\Container\Attributes\Singleton;
use Lomkit\Rest\Exceptions\InvalidExtensionException;

/** @internal */
#[Singleton]
final class RuntimeExtensions
{
    private const string SOURCE = 'Rest::registerExtensions()';

    /** @var list<class-string<Extension>> */
    private array $classes = [];

    private bool $frozen = false;

    public function register(string $class): void
    {
        $this->add($this->accepted($class));
    }

    /** @param array<array-key, mixed> $classes */
    public function registerAll(array $classes): void
    {
        if (! array_is_list($classes)) {
            throw InvalidExtensionException::notAList(self::SOURCE);
        }

        $accepted = [];

        foreach ($classes as $class) {
            if (is_object($class)) {
                throw InvalidExtensionException::instanceGiven(self::SOURCE, $class::class);
            }

            if (! is_string($class)) {
                throw InvalidExtensionException::notAClassName(self::SOURCE, get_debug_type($class));
            }

            $accepted[] = $this->accepted($class);
        }

        foreach ($accepted as $class) {
            $this->add($class);
        }
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    /** @return list<class-string<Extension>> */
    public function all(): array
    {
        return $this->classes;
    }

    /** @return class-string<Extension> */
    private function accepted(string $class): string
    {
        if ($this->frozen) {
            throw InvalidExtensionException::lateRegistration($class);
        }

        $class = ExtensionClass::ensureValid($class);
        ExtensionName::of($class);

        return $class;
    }

    /** @param class-string<Extension> $class */
    private function add(string $class): void
    {
        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
        }
    }
}
