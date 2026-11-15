<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;

/** @internal */
final readonly class ExtensionList
{
    public const string CONFIG = 'The [rest.extensions] config';

    public const string DISABLED = 'The [rest.disabled_extensions] config';

    /**
     * @param  list<ExtensionEntry>  $entries
     * @param  list<InvalidExtensionException>  $refused
     * @param  list<string>  $idle
     */
    private function __construct(
        public array $entries,
        public array $refused,
        public array $idle,
    ) {}

    /**
     * @param  list<mixed>  $configured
     * @param  list<mixed>  $disabled
     * @param  list<string>  $registered
     */
    public static function of(array $configured, array $disabled, array $registered): self
    {
        $entries = [];
        $refused = [];
        $idle = [];

        foreach ($registered as $class) {
            $entries[$class] = new ExtensionEntry($class, ExtensionOrigin::Runtime);
        }

        foreach (self::classes(self::CONFIG, $configured, $refused) as $class) {
            $entries[$class] = new ExtensionEntry($class, ExtensionOrigin::Config);
        }

        foreach (self::classes(self::DISABLED, $disabled, $refused) as $class) {
            if (isset($entries[$class])) {
                $entries[$class] = $entries[$class]->disable();
            } elseif (! in_array($class, $idle, true)) {
                $idle[] = $class;
            }
        }

        return new self(array_values($entries), $refused, $idle);
    }

    public function named(): NamedExtensions
    {
        return NamedExtensions::of($this->entries, $this->refused);
    }

    /**
     * @param  list<mixed>  $values
     * @param  list<InvalidExtensionException>  $refused
     * @return list<string>
     */
    private static function classes(string $source, array $values, array &$refused): array
    {
        $classes = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $classes[] = ExtensionClass::canonical($value);
            } else {
                $refused[] = is_object($value) ? InvalidExtensionException::instanceGiven($source, $value::class) : InvalidExtensionException::notAClassName($source, get_debug_type($value));
            }
        }

        return $classes;
    }
}
