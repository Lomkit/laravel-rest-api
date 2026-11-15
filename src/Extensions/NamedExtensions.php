<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;

/** @internal */
final readonly class NamedExtensions
{
    /**
     * @param  list<ExtensionEntry>  $entries
     * @param  list<InvalidExtensionException>  $refused
     * @param  array<string, class-string<Extension>>  $classes
     * @param  list<string>  $shared
     */
    private function __construct(
        public array $entries,
        public array $refused,
        private array $classes,
        public array $shared,
    ) {}

    /**
     * @param  list<ExtensionEntry>  $entries
     * @param  list<InvalidExtensionException>  $refused
     */
    public static function of(array $entries, array $refused = []): self
    {
        $named = [];
        $byName = [];

        foreach ($entries as $entry) {
            if ($entry->disabled) {
                $named[] = $entry;

                continue;
            }

            try {
                $class = ExtensionClass::ensureValid($entry->class);
                $name = ExtensionName::of($class);
            } catch (InvalidExtensionException $exception) {
                $refused[] = $exception;

                continue;
            }

            $named[] = $entry->named($name);
            $byName[$name][] = $class;
        }

        $classes = [];
        $shared = [];

        foreach ($byName as $name => $sharing) {
            if (count($sharing) > 1) {
                $refused[] = InvalidExtensionException::sharedName($name, $sharing);
                $shared[] = $name;
            } else {
                $classes[$name] = $sharing[0];
            }
        }

        return new self($named, $refused, $classes, $shared);
    }

    /** @return array<string, class-string<Extension>> */
    public function enabled(): array
    {
        if ($this->refused !== []) {
            throw $this->refused[0];
        }

        return $this->classes;
    }
}
