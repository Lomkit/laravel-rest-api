<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\Validator;

final readonly class Utf8Input
{
    /** @param list<string> $paths */
    private function __construct(private array $paths) {}

    /** @param array<array-key, mixed> $input */
    public static function of(array $input): self
    {
        return new self(self::invalid($input, ''));
    }

    public function isValid(): bool
    {
        return $this->paths === [];
    }

    public function validator(Factory $factory): Validator
    {
        $rules = [];
        $names = [];

        foreach ($this->paths as $path) {
            $rules[$path] = [new Rejected(Violation::notUtf8($path))];
            $names[$path] = KeyPath::unescape($path);
        }

        return $factory->make([], $rules, [], $names);
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    private static function invalid(array $input, string $prefix): array
    {
        $paths = [];

        foreach ($input as $key => $value) {
            $valid = is_int($key) || mb_check_encoding($key, 'UTF-8');
            $path = $prefix.KeyPath::segment($valid ? $key : mb_scrub($key, 'UTF-8'));

            if (! $valid || (is_string($value) && ! mb_check_encoding($value, 'UTF-8'))) {
                $paths[] = $path;

                continue;
            }

            if (is_array($value)) {
                $paths = [...$paths, ...self::invalid($value, $path.'.')];
            }
        }

        return $paths;
    }
}
