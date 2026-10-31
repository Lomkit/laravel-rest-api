<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class MorphTargets
{
    /** @param non-empty-list<class-string<Resource>> $resources */
    private function __construct(private array $resources) {}

    /** @param class-string<Resource> $resource */
    public static function of(string $resource): self
    {
        return new self([$resource]);
    }

    /** @return non-empty-list<class-string<Resource>> */
    public function resources(): array
    {
        return $this->resources;
    }

    /** @return list<class-string<Model>> */
    public function modelClasses(): array
    {
        return array_map(fn (string $resource): string => $resource::modelClass(), $this->resources);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_map(fn (string $resource): string => $resource::newModel()->getMorphClass(), $this->resources);
    }

    public function isPolymorphic(): bool
    {
        return count($this->resources) > 1;
    }
}
