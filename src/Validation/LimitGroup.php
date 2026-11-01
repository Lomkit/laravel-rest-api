<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Config\ConfigValue;
use Lomkit\Rest\Exceptions\InvalidConfigException;

final readonly class LimitGroup
{
    /** @param array<array-key, mixed> $values */
    private function __construct(
        private string $path,
        private array $values,
    ) {}

    /** @param list<string> $keys */
    public static function of(mixed $limits, string $name, array $keys): self
    {
        $values = is_array($limits) ? ($limits[$name] ?? null) : null;
        $values = is_array($values) ? $values : [];

        self::refuseUnknown("rest.limits.{$name}", $values, $keys);

        return new self("rest.limits.{$name}", $values);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $keys
     */
    public static function refuseUnknown(string $path, array $values, array $keys): void
    {
        foreach (array_keys($values) as $key) {
            if (! in_array($key, $keys, true)) {
                throw InvalidConfigException::unknown("{$path}.{$key}");
            }
        }
    }

    public function read(string $key): int
    {
        return ConfigValue::at("{$this->path}.{$key}", $this->values[$key] ?? null)->integer();
    }
}
