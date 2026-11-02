<?php

namespace Lomkit\Rest\Validation;

final readonly class Cursor
{
    private function __construct(
        public string $path,
        public string $pattern,
        public mixed $value,
        public bool $present,
        private Violations $violations,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function of(array $data, Violations $violations): self
    {
        return new self('', '', $data, true, $violations);
    }

    public function at(int|string $key): self
    {
        return $this->step($key, KeyPath::segment($key));
    }

    public function item(int $index): self
    {
        return $this->step($index, '*');
    }

    /** @return list<self>|null */
    public function items(int $max): ?array
    {
        if (! is_array($this->value)) {
            return null;
        }

        if (! array_is_list($this->value)) {
            $this->violations->add(Violation::notList($this->path));

            return null;
        }

        if (count($this->value) > $max) {
            $this->violations->add(Violation::tooMany($this->path, $max));

            return null;
        }

        return array_map(fn (int $index): self => $this->item($index), array_keys($this->value));
    }

    /** @param array<string, string> $replace */
    public function reject(string $message, array $replace = []): void
    {
        $this->violations->add(new Violation($this->path, $message, $replace));
    }

    private function step(int|string $key, string $segment): self
    {
        $path = $this->path === '' ? KeyPath::segment($key) : $this->path.'.'.KeyPath::segment($key);
        $pattern = $this->pattern === '' ? $segment : $this->pattern.'.'.$segment;

        if (! is_array($this->value) || ! array_key_exists($key, $this->value)) {
            return new self($path, $pattern, null, false, $this->violations);
        }

        return new self($path, $pattern, $this->value[$key], true, $this->violations);
    }
}
