<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Routing\ResourceRegistrar as IlluminateResourceRegistrar;

final readonly class ResourceVerbs
{
    /** @param array<string, string> $verbs */
    private function __construct(private array $verbs) {}

    public static function current(): self
    {
        $configured = IlluminateResourceRegistrar::verbs();
        $verbs = [];

        foreach (Endpoint::cases() as $endpoint) {
            $key = $endpoint->verbKey();

            if ($key === null) {
                continue;
            }

            $verbs[$key] = UriSegment::verb($key, $configured[$key] ?? $endpoint->defaultVerb())->value;
        }

        return new self($verbs);
    }

    public function for(Endpoint $endpoint): ?string
    {
        $key = $endpoint->verbKey();

        return $key === null ? null : $this->verbs[$key];
    }
}
