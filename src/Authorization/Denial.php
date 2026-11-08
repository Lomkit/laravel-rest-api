<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Support\Translation;

use function Illuminate\Support\enum_value;

final readonly class Denial
{
    /** @param class-string<Resource> $resource */
    public function __construct(
        public string $path,
        public string $ability,
        public string $resource,
        public int|string|null $key,
        public Decision $decision,
    ) {}

    public static function of(Check $check, Decision $decision): self
    {
        return new self($check->path, $check->ability, $check->resource::class, self::keyOf($check->row ?? $check->subject), $decision);
    }

    public function message(): string
    {
        return $this->decision->message ?? Translation::line('rest::errors.unauthorized');
    }

    /** @return array{path: string, ability: string, key?: int|string, message: string} */
    public function toArray(): array
    {
        $denial = ['path' => $this->path, 'ability' => $this->ability];

        if ($this->key !== null) {
            $denial['key'] = $this->key;
        }

        return [...$denial, 'message' => $this->message()];
    }

    private static function keyOf(Model|string $subject): int|string|null
    {
        if (! $subject instanceof Model || ! $subject->exists) {
            return null;
        }

        $key = enum_value($subject->getKey());

        return is_int($key) || is_string($key) ? $key : null;
    }
}
