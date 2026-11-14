<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;

final readonly class ExtensionList
{
    /**
     * @param  list<ExtensionEntry>  $entries
     * @param  list<InvalidExtensionException>  $refused
     */
    private function __construct(
        public array $entries,
        public array $refused,
    ) {}

    /**
     * @param  array<array-key, mixed>  $configured
     * @param  array<string, string>  $registered
     */
    public static function merge(array $configured, array $registered): self
    {
        $entries = [];
        $refused = [];

        foreach ($registered as $name => $class) {
            $entries[$name] = new ExtensionEntry($name, $class, ExtensionOrigin::Runtime, false);
        }

        foreach ($configured as $name => $value) {
            try {
                $entry = self::configured($name, $value, $entries[$name] ?? null);
            } catch (InvalidExtensionException $exception) {
                $refused[] = $exception;

                continue;
            }

            $entries[$entry->name] = $entry;
        }

        return new self(array_values($entries), $refused);
    }

    /** @return array<string, string> */
    public function enabled(): array
    {
        if ($this->refused !== []) {
            throw $this->refused[0];
        }

        $enabled = [];

        foreach ($this->entries as $entry) {
            if (! $entry->disabled && $entry->class !== null) {
                $enabled[$entry->name] = $entry->class;
            }
        }

        return $enabled;
    }

    private static function configured(int|string $name, mixed $value, ?ExtensionEntry $registered): ExtensionEntry
    {
        if (! is_string($name)) {
            throw InvalidExtensionException::missingName(is_string($value) ? $value : get_debug_type($value));
        }

        ExtensionName::ensureValid($name);

        if ($value === false) {
            return new ExtensionEntry($name, $registered?->class, $registered->origin ?? ExtensionOrigin::Config, true);
        }

        if (is_object($value)) {
            throw InvalidExtensionException::instanceGiven($name, $value::class);
        }

        if (! is_string($value)) {
            throw InvalidExtensionException::notAClass($name, get_debug_type($value));
        }

        return new ExtensionEntry($name, $value, ExtensionOrigin::Config, false);
    }
}
