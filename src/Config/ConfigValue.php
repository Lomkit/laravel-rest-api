<?php

namespace Lomkit\Rest\Config;

use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Exceptions\InvalidConfigException;

final readonly class ConfigValue
{
    private function __construct(
        private string $key,
        private mixed $value,
    ) {}

    public static function of(Repository $config, string $key): self
    {
        return new self($key, $config->get($key));
    }

    public static function at(string $key, mixed $value): self
    {
        return new self($key, $value);
    }

    public function boolean(): bool
    {
        return match ($this->value) {
            true, '1', 'true' => true,
            false, '0', 'false' => false,
            default => throw InvalidConfigException::mustBe($this->key, 'a boolean'),
        };
    }

    public function integer(): int
    {
        if (is_int($this->value) && $this->value >= 0) {
            return $this->value;
        }

        if (is_string($this->value) && ctype_digit($this->value)) {
            return (int) $this->value;
        }

        throw InvalidConfigException::mustBe($this->key, 'an integer of 0 or more');
    }

    public function string(): string
    {
        if (is_string($this->value) && $this->value !== '') {
            return $this->value;
        }

        throw InvalidConfigException::mustBe($this->key, 'a non-empty string');
    }

    public function nullableString(): ?string
    {
        if ($this->value === null || (is_string($this->value) && $this->value !== '')) {
            return $this->value;
        }

        throw InvalidConfigException::mustBe($this->key, 'null or a non-empty string');
    }

    /**
     * @param  list<string>  $required
     * @return array<string, string>
     */
    public function strings(array $required): array
    {
        if (! is_array($this->value)) {
            throw InvalidConfigException::mustBe($this->key, 'a map of strings');
        }

        $strings = [];

        foreach ($this->value as $name => $string) {
            if (! is_string($name)) {
                throw InvalidConfigException::mustBe($this->key, 'a map of strings');
            }

            if (! is_string($string)) {
                throw InvalidConfigException::mustBe("{$this->key}.{$name}", 'a string');
            }

            $strings[$name] = $string;
        }

        foreach ($required as $name) {
            if (! isset($strings[$name])) {
                throw InvalidConfigException::mustBe("{$this->key}.{$name}", 'a string');
            }
        }

        return $strings;
    }
}
