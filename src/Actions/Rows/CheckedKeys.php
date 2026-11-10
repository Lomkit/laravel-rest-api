<?php

namespace Lomkit\Rest\Actions\Rows;

use Lomkit\Rest\Fields\ID;

use function Illuminate\Support\enum_value;

final readonly class CheckedKeys
{
    /** @param array<int|string, true> $keys */
    public function __construct(
        private ID $id,
        private array $keys,
    ) {}

    public static function normalize(ID $id, mixed $value): int|string|null
    {
        $key = $id->parseKey(enum_value($value));

        return $key === null ? null : $id->normalizeKey($key);
    }

    public function has(mixed $value): bool
    {
        $key = self::normalize($this->id, $value);

        return $key !== null && isset($this->keys[$key]);
    }

    public function count(): int
    {
        return count($this->keys);
    }
}
