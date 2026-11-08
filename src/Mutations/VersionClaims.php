<?php

namespace Lomkit\Rest\Mutations;

use Lomkit\Rest\Exceptions\VersionConflict;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\KeysGrammar;

final class VersionClaims
{
    /** @var list<array{Resource, RowVersion, string, int|string, string}> */
    private array $claims = [];

    /** @param int|string|list<int|string> $keys */
    public function claim(Resource $resource, RowVersion $version, string $path, int|string|array $keys, string $value): void
    {
        foreach (is_array($keys) ? $keys : [$keys] as $key) {
            $this->claims[] = [$resource, $version, $path, $key, $value];
        }
    }

    public function verify(): void
    {
        $current = $this->current();
        $conflicts = [];

        foreach ($this->claims as [$resource, , $path, $key, $value]) {
            $stored = $current[$resource::class][KeysGrammar::idOf($resource)->normalizeKey($key)] ?? null;

            if ($stored !== $value) {
                $conflicts[] = ['path' => $path, 'key' => $key, 'version' => $stored];
            }
        }

        if ($conflicts !== []) {
            throw new VersionConflict($conflicts);
        }
    }

    /** @return array<class-string<Resource>, array<array-key, mixed>> */
    private function current(): array
    {
        $groups = [];

        foreach ($this->claims as [$resource, $version, , $key]) {
            $groups[$resource::class] ??= [$resource, $version, []];
            $groups[$resource::class][2][] = $key;
        }

        $current = [];

        foreach ($groups as $class => [$resource, $version, $keys]) {
            $current[$class] = $version->lock($resource, array_values(array_unique($keys)));
        }

        return $current;
    }
}
