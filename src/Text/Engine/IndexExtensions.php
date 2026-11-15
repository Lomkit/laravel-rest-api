<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class IndexExtensions
{
    /** @param class-string<Resource>|null $resource */
    public function __construct(
        private readonly ?string $resource = null,
        private readonly ?RestContext $context = null,
    ) {}

    public static function of(Resource $resource): self
    {
        return new self($resource::class, $resource->context());
    }

    public function profile(object $engine): ?EngineProfile
    {
        if ($this->resource === null || $this->context === null) {
            return null;
        }

        $providers = $this->context->extensions()->applicable($this->resource, ProvidesEngineProfiles::class);

        if ($providers === []) {
            return null;
        }

        ksort($providers);
        $context = new EngineProfileContext($this->resource, $engine);
        $claims = [];

        foreach ($providers as $name => $provider) {
            $profile = $provider->engineProfile($context);

            if ($profile !== null) {
                $claims[$name] = [$provider::class, $profile];
            }
        }

        return self::single($this->resource, $engine, $claims);
    }

    /** @param array<string, array{class-string, EngineProfile}> $claims */
    private static function single(string $resource, object $engine, array $claims): ?EngineProfile
    {
        $names = array_keys($claims);

        if (count($names) > 1) {
            [$first, $second] = $names;

            throw InvalidEngineExtensionException::conflictingProfiles($engine::class, $resource, $first, $claims[$first][0], $second, $claims[$second][0]);
        }

        return $names === [] ? null : $claims[$names[0]][1];
    }
}
