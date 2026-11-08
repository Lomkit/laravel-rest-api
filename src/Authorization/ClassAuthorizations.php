<?php

namespace Lomkit\Rest\Authorization;

use JsonSerializable;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;

final readonly class ClassAuthorizations implements JsonSerializable
{
    /** @param array<string, Decision> $decisions */
    private function __construct(private array $decisions) {}

    public static function of(Resource $resource): self
    {
        return self::on($resource, self::abilities($resource));
    }

    /** @param list<string> $abilities */
    public static function on(Resource $resource, array $abilities): self
    {
        $checks = array_map(fn (string $ability): Check => Check::onClass($resource, $ability), $abilities);

        return new self(array_combine($abilities, $resource->context()->authorizer()->inspectAll($checks)));
    }

    /** @param list<string> $abilities */
    public function allow(array $abilities): bool
    {
        return array_all($abilities, fn (string $ability): bool => ($this->decisions[$ability] ?? null)?->allowed === true);
    }

    /** @return array<string, array{allowed: bool, message: string|null}> */
    #[\Override]
    public function jsonSerialize(): array
    {
        return array_map(fn (Decision $decision): array => ['allowed' => $decision->allowed, 'message' => $decision->message], $this->decisions);
    }

    /** @return list<string> */
    private static function abilities(Resource $resource): array
    {
        $definition = $resource->definition();

        return array_values(array_unique([
            'viewAny',
            'create',
            ...array_merge(...array_map($resource->abilities(...), Endpoint::cases())),
            ...array_merge([], ...array_map(fn (Action $action): array => $action->entryAbilities(), array_values($definition->actions()))),
            ...array_merge([], ...array_map(fn (Metric $metric): array => $metric->entryAbilities(), array_values($definition->metrics()))),
        ]));
    }
}
